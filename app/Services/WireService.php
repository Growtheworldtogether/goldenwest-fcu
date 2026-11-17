<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Payee;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WireTransfer;
use App\Services\Ledger\TransferException;
use App\Services\Ledger\TransferRequest;
use App\Services\Ledger\TransferService;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Outgoing wires. Only members with the `wires` feature can send one. Every wire waits for staff review,
 * the amount and fee are set aside at once, and a wire sent after the cutoff goes out the next business day.
 */
final class WireService
{
    public function __construct(private TransferService $ledger)
    {
    }

    public static function fee(): Money
    {
        return Money::cents((int) Setting::value('wire_fee_cents', config('bank.wire_fee')));
    }

    public static function cutoff(): string
    {
        return Setting::value('wire_cutoff', config('bank.wire_cutoff'));
    }

    /** The business day this wire is sent: today before the cutoff on a weekday, otherwise the next weekday. */
    public static function processOn(?CarbonInterface $now = null): Carbon
    {
        $local = Carbon::instance($now ?? now())->timezone(config('bank.wire_timezone'));
        [$h, $m] = array_map('intval', explode(':', self::cutoff()));
        $day = $local->copy()->startOfDay();

        if ($local->isWeekday() && $local->lt($day->copy()->setTime($h, $m))) {
            return $day;
        }

        return $day->addWeekday();
    }

    public function send(User $member, Account $from, Payee $payee, Money $amount, string $address, string $purpose, string $token, ?int $impersonatorId = null): Transaction
    {
        if (! Features::enabled($member, 'wires')) {
            throw TransferException::because('feature_off', 'Wire transfers are not turned on for your membership. Call Customer Care to ask about them.');
        }
        if ($member->kyc_status !== 'verified') {
            throw TransferException::because('kyc_required', 'Verify your identity before you send a wire.');
        }
        PayeeService::assertUsable($payee, $member);

        $on = self::processOn();
        $tx = $this->ledger->execute(new TransferRequest(
            type: TransactionType::WireOut,
            amount: $amount,
            idempotencyKey: 'wire:'.$member->id.':'.$token,
            fromAccountId: $from->id,
            fee: self::fee(),
            initiatedBy: $member->id,
            impersonatorId: $impersonatorId,
            memo: $purpose,
            memberDescription: 'Wire to '.$payee->name,
            payeeId: $payee->id,
            effectiveDate: $on->toDateString(),
        ));

        if (! WireTransfer::where('transaction_id', $tx->id)->exists()) {
            $wire = WireTransfer::create(['user_id' => $member->id, 'transaction_id' => $tx->id, 'payee_id' => $payee->id, 'recipient_address' => $address, 'purpose' => $purpose, 'process_on' => $on->toDateString()]);
            Audit::record('wire.requested', $wire, null, ['amount' => $amount->cents, 'fee' => self::fee()->cents, 'payee' => $payee->id, 'process_on' => $on->toDateString()], $member->id, $impersonatorId);
        }

        return $tx;
    }
}
