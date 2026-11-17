<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Payee;
use App\Models\User;
use App\Services\Ledger\TransferException;
use App\Services\Ledger\TransferRequest;
use App\Services\Ledger\TransferService;
use App\Support\Money;
use App\Support\Routing;
use Illuminate\Validation\ValidationException;

/** External accounts. A new one has a cooling-off period before first use, then can be sent to or pulled from. */
final class PayeeService
{
    public function __construct(private TransferService $ledger)
    {
    }

    public function add(User $member, array $data): Payee
    {
        if (! Routing::valid($data['routing_number'])) {
            throw ValidationException::withMessages(['routing_number' => 'That routing number is not valid. Check the nine digits at the bottom left of a check.']);
        }
        foreach (Payee::where('user_id', $member->id)->where('status', 'active')->where('routing_number', $data['routing_number'])->get() as $p) {
            if ($p->account_number === $data['account_number']) {
                throw ValidationException::withMessages(['account_number' => 'You have already added this account.']);
            }
        }

        $payee = Payee::create([
            'user_id' => $member->id,
            'name' => $data['name'],
            'bank_name' => $data['bank_name'] ?? null,
            'routing_number' => $data['routing_number'],
            'account_number' => $data['account_number'],
            'last4' => substr($data['account_number'], -4),
            'account_type' => $data['account_type'],
            'activates_at' => now()->addHours((int) config('bank.payee_cooling_hours')),
        ]);
        Audit::record('payee.added', $payee, null, ['name' => $payee->name, 'last4' => $payee->last4, 'activates_at' => $payee->activates_at->toIso8601String()], $member->id);

        return $payee;
    }

    public function remove(Payee $payee, User $by): void
    {
        $payee->update(['status' => 'removed']);
        Audit::record('payee.removed', $payee, ['status' => 'active'], ['status' => 'removed'], $by->id);
    }

    /** Staff can end the waiting period early, for example after calling the member back. Always audited. */
    public function waiveWait(Payee $payee, User $staff): void
    {
        $old = $payee->activates_at->toIso8601String();
        $payee->update(['activates_at' => now(), 'waived_by' => $staff->id]);
        Audit::record('payee.cooling_off_waived', $payee, ['activates_at' => $old], ['activates_at' => now()->toIso8601String()], $staff->id);
    }

    public static function assertUsable(Payee $payee, User $member): void
    {
        if ($payee->user_id !== $member->id || $payee->status !== 'active') {
            throw TransferException::because('payee_unknown', 'Choose one of your saved external accounts.');
        }
        if (! $payee->isActive()) {
            throw TransferException::because('payee_cooling_off', 'For your protection, a new external account can be used after '.$payee->activates_at->timezone(config('app.display_timezone', 'America/Los_Angeles'))->format('M j \a\t g:i a T').'.');
        }
    }

    /** Send money out by ACH. Limits apply and larger amounts wait for review. Arrives the next business day. */
    public function send(User $member, Account $from, Payee $payee, Money $amount, ?string $memo, string $token, ?int $impersonatorId = null)
    {
        self::assertUsable($payee, $member);

        $tx = $this->ledger->execute(new TransferRequest(
            type: TransactionType::AchOut,
            amount: $amount,
            idempotencyKey: 'ach-out:'.$member->id.':'.$token,
            fromAccountId: $from->id,
            initiatedBy: $member->id,
            impersonatorId: $impersonatorId,
            memo: $memo,
            memberDescription: 'Transfer to '.$payee->label(),
            payeeId: $payee->id,
            effectiveDate: now()->toDateString(),
        ));
        Audit::record('payee.ach_sent', $payee, null, ['amount' => $amount->cents, 'transaction' => $tx->reference], $member->id, $impersonatorId);

        return $tx;
    }
}
