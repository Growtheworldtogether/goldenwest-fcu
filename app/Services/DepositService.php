<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\AccountHolder;
use App\Models\Deposit;
use App\Models\Payee;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Ledger\TransferException;
use App\Services\Ledger\TransferRequest;
use App\Services\Ledger\TransferService;
use App\Support\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Money coming in. Check and ACH-pull deposits always wait for staff review, so nothing is credited until a
 * person approves it. Cash is recorded by a teller and posts at once, unless it is a large amount.
 * Every posting goes through TransferService.
 */
final class DepositService
{
    public function __construct(private TransferService $ledger, private SecureUpload $uploads)
    {
    }

    public function check(User $member, Account $account, Money $amount, ?string $checkNumber, UploadedFile $front, UploadedFile $back, string $token, ?int $impersonatorId = null): Deposit
    {
        $this->assertVerified($member);
        $this->assertLimit($amount, config('bank.check_deposit_limit'), 'check');

        $key = 'deposit:check:'.$member->id.':'.$token;
        if ($existing = $this->existing($key)) {
            return $existing;
        }

        $frontFile = $this->uploads->store($front, 'checks', 'front');
        $backFile = $this->uploads->store($back, 'checks', 'back');

        try {
            $tx = $this->ledger->execute(new TransferRequest(
                type: TransactionType::Deposit,
                amount: $amount,
                idempotencyKey: $key,
                toAccountId: $account->id,
                initiatedBy: $member->id,
                impersonatorId: $impersonatorId,
                memo: $checkNumber ? 'Check #'.$checkNumber : null,
                memberDescription: 'Check deposit',
                requireReview: true,
            ));
        } catch (TransferException $e) {
            Storage::disk('local')->delete([$frontFile['path'], $backFile['path']]);
            throw $e;
        }

        $deposit = Deposit::create([
            'user_id' => $member->id, 'account_id' => $account->id, 'transaction_id' => $tx->id, 'method' => 'check',
            'check_number' => $checkNumber, 'front_path' => $frontFile['path'], 'back_path' => $backFile['path'],
        ]);
        Audit::record('deposit.check_submitted', $deposit, null, ['amount' => $amount->cents, 'account' => $account->id], $member->id, $impersonatorId);

        return $deposit;
    }

    /** Pull money from the member's own account at another bank. Settles on the next business day, after review. */
    public function pull(User $member, Account $account, Payee $payee, Money $amount, string $token, ?int $impersonatorId = null): Deposit
    {
        $this->assertVerified($member);
        $this->assertLimit($amount, config('bank.ach_pull_limit'), 'transfer');
        PayeeService::assertUsable($payee, $member);

        $key = 'deposit:ach:'.$member->id.':'.$token;
        if ($existing = $this->existing($key)) {
            return $existing;
        }

        $tx = $this->ledger->execute(new TransferRequest(
            type: TransactionType::Deposit,
            amount: $amount,
            idempotencyKey: $key,
            toAccountId: $account->id,
            initiatedBy: $member->id,
            impersonatorId: $impersonatorId,
            memo: 'ACH pull from '.$payee->label(),
            memberDescription: 'Transfer from '.$payee->label(),
            requireReview: true,
            payeeId: $payee->id,
            effectiveDate: now()->addWeekday()->toDateString(),
        ));

        $deposit = Deposit::create(['user_id' => $member->id, 'account_id' => $account->id, 'transaction_id' => $tx->id, 'method' => 'ach', 'payee_id' => $payee->id]);
        Audit::record('deposit.ach_requested', $deposit, null, ['amount' => $amount->cents, 'payee' => $payee->id], $member->id, $impersonatorId);

        return $deposit;
    }

    /** A teller records cash handed over at the counter. Amounts at or over the threshold wait for a second person. */
    public function cash(User $teller, Account $account, Money $amount, ?string $note, string $token): Deposit
    {
        $key = 'deposit:cash:'.$teller->id.':'.$token;
        if ($existing = $this->existing($key)) {
            return $existing;
        }

        $holder = AccountHolder::where('account_id', $account->id)->where('status', 'active')->orderBy('id')->value('user_id');
        $big = $amount->cents >= config('bank.cash_review_threshold');

        $tx = $this->ledger->execute(new TransferRequest(
            type: TransactionType::Deposit,
            amount: $amount,
            idempotencyKey: $key,
            toAccountId: $account->id,
            initiatedBy: $teller->id,
            memo: 'Cash deposit recorded by '.$teller->name.($note ? ': '.$note : ''),
            memberDescription: 'Cash deposit',
            requireReview: $big,
        ));

        $deposit = Deposit::create(['user_id' => $holder, 'account_id' => $account->id, 'transaction_id' => $tx->id, 'method' => 'cash', 'recorded_by' => $teller->id]);
        Audit::record('deposit.cash_recorded', $deposit, null, ['amount' => $amount->cents, 'account' => $account->id, 'needs_review' => $big], $teller->id);

        return $deposit;
    }

    private function existing(string $key): ?Deposit
    {
        $tx = Transaction::where('idempotency_key', $key)->first();

        return $tx ? Deposit::where('transaction_id', $tx->id)->first() : null;
    }

    private function assertVerified(User $member): void
    {
        if ($member->kyc_status !== 'verified') {
            throw TransferException::because('kyc_required', 'Verify your identity before you deposit a check or move money from another bank.');
        }
    }

    private function assertLimit(Money $amount, int $limit, string $what): void
    {
        if ($amount->cents <= 0) {
            throw TransferException::because('invalid_amount', 'The amount must be greater than zero.');
        }
        if ($amount->cents > $limit) {
            throw TransferException::because('limit_exceeded', 'The most you can send as one '.$what.' is '.Money::cents($limit)->format().'. For more, visit a branch.');
        }
    }
}
