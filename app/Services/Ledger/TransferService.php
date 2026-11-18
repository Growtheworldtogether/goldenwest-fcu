<?php

namespace App\Services\Ledger;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\AccountHolder;
use App\Models\AccountRestriction;
use App\Models\Decision;
use App\Models\LedgerEntry;
use App\Models\ReasonCode;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Audit;
use App\Services\StaffDirectory;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The only code allowed to change a balance. Every screen, job and admin action calls it.
 *
 * Accounts are always locked in ascending id order inside one database transaction, so two
 * transfers that cross each other cannot deadlock and two requests cannot overdraw an account.
 * Balances are a cache of the ledger: `php artisan ledger:verify` recomputes them.
 */
final class TransferService
{
    /** Run a request: check the rules, cover a shortfall, then post it or reserve it for review. */
    public function execute(TransferRequest $request): Transaction
    {
        $this->assertShape($request);

        return $this->atomic(function () use ($request) {
            $accounts = $this->lockAccounts($request);

            if ($existing = Transaction::where('idempotency_key', $request->idempotencyKey)->first()) {
                $this->assertSameRequest($existing, $request);

                return $existing;
            }

            $from = $request->fromAccountId ? $accounts[$request->fromAccountId] : null;
            $to = $request->toAccountId ? $accounts[$request->toAccountId] : null;

            $this->assertUsable($from, $to);

            if ($from && $request->type->isLimited()) {
                $this->assertLimits($request, $from);
            }

            $need = $request->amount->add($request->feeOrZero());
            if ($from && $from->available_balance->lessThan($need)) {
                $this->coverShortfall($from, $accounts, $need, $request);
            }

            $transaction = $this->createTransaction($request);

            if ($this->needsReview($request)) {
                if ($from) {
                    $this->reserve($from, $need);
                }
                Audit::record('transaction.pending', $transaction, null, ['amount' => $request->amount->cents, 'type' => $request->type->value], $request->initiatedBy, $request->impersonatorId);

                return $transaction;
            }

            $this->post($transaction, $accounts, reserved: false);

            return $transaction->refresh();
        });
    }

    /** Set money aside on an account for a card authorization. Refuses if the account is restricted or the funds are not there. */
    /**
     * Write past, already-settled activity into an account that has never had any, so a new account opens with history.
     * Rows must be in date order and never take the balance below zero. The ledger stays append-only and in date order.
     *
     * @param  list<array{type: TransactionType, direction: string, amount: int, at: CarbonInterface, description: string}>  $rows
     */
    public function postHistory(int $accountId, array $rows, User|int $staff): int
    {
        $staffId = $staff instanceof User ? $staff->id : $staff;

        return $this->atomic(function () use ($accountId, $rows, $staffId) {
            $account = Account::lockForUpdate()->findOrFail($accountId);
            if ($account->type !== 'standard' || $account->status !== 'active') {
                throw TransferException::because('not_eligible', 'History can only be added to an open account.');
            }
            if (LedgerEntry::where('account_id', $account->id)->exists() || $account->balance->cents !== 0) {
                throw TransferException::because('not_new', 'This account already has activity. History can only be added to an account that has none.');
            }

            $balance = 0;
            $last = null;
            foreach ($rows as $i => $row) {
                $at = $row['at'];
                if ($last && $at->lessThan($last)) {
                    throw TransferException::because('bad_order', 'History rows must be in date order.');
                }
                $last = $at;
                $credit = $row['direction'] === 'credit';
                $balance += $credit ? $row['amount'] : -$row['amount'];
                if ($balance < 0 || $row['amount'] <= 0) {
                    throw TransferException::because('bad_row', 'History cannot take the balance below zero.');
                }
                $tx = new Transaction;
                $tx->forceFill([
                    'reference' => (string) Str::ulid(), 'idempotency_key' => 'history:'.$account->id.':'.$i, 'type' => $row['type'],
                    'from_account_id' => $credit ? null : $account->id, 'to_account_id' => $credit ? $account->id : null,
                    'amount' => Money::cents($row['amount']), 'fee' => Money::zero(), 'status' => TransactionStatus::Completed,
                    'initiated_by' => $staffId, 'effective_date' => $at->toDateString(), 'memo' => 'Opening history added by staff',
                    'member_description' => $row['description'], 'posted_at' => $at, 'created_at' => $at, 'updated_at' => $at,
                ])->save();
                LedgerEntry::create(['transaction_id' => $tx->id, 'account_id' => $account->id, 'direction' => $row['direction'], 'amount' => $row['amount'], 'balance_after' => $balance, 'created_at' => $at, 'updated_at' => $at]);
            }

            $account->balance = $balance;
            $account->available_balance = $balance;
            $account->save();

            return $balance;
        });
    }

    public function holdFunds(int $accountId, Money $amount): void
    {
        $this->atomic(function () use ($accountId, $amount) {
            $account = $this->lockIds(collect([$accountId]))[$accountId];
            $this->assertUsable($account, null);
            if ($account->available_balance->lessThan($amount)) {
                throw TransferException::because('insufficient_funds', 'There is not enough available money in this account.');
            }
            $this->reserve($account, $amount);
        });
    }

    /** Give back money set aside for an authorization that was not used. */
    public function releaseFunds(int $accountId, Money $amount): void
    {
        $this->atomic(function () use ($accountId, $amount) {
            $account = $this->lockIds(collect([$accountId]))[$accountId];
            $this->releaseReserve($account, $amount);
        });
    }

    /** Approve a pending or held transaction. Large amounts need a second, different admin. */
    public function approve(Transaction $transaction, User|int $admin, ?string $note = null): Transaction
    {
        return $this->tellMember($this->approveInner($transaction, $admin, $note));
    }

    private function approveInner(Transaction $transaction, User|int $admin, ?string $note = null): Transaction
    {
        $adminId = $admin instanceof User ? $admin->id : $admin;

        return $this->atomic(function () use ($transaction, $adminId, $note) {
            $accounts = $this->lockForTransaction($transaction);
            $tx = $this->lockOpen($transaction);

            $superAdmin = StaffDirectory::isSuperAdmin($adminId);
            if (! $superAdmin && ($tx->initiated_by === $adminId || $tx->impersonator_id === $adminId)) {
                throw TransferException::because('self_approval', 'You cannot approve a transaction you started.');
            }

            Decision::create([
                'decidable_type' => $tx->getMorphClass(), 'decidable_id' => $tx->id,
                'decision' => 'approved', 'reason_text' => $note, 'decided_by' => $adminId,
            ]);

            // A Super Admin's approval is final on its own, whatever the amount.
            $needed = ! $superAdmin && $tx->amount->cents >= config('bank.dual_approval_threshold') ? 2 : 1;
            $approvers = $tx->decisions()->where('decision', 'approved')->distinct()->count('decided_by');

            if ($approvers < $needed) {
                Audit::record('transaction.approval_recorded', $tx, null, ['approvers' => $approvers, 'needed' => $needed], $adminId);

                return $tx->refresh();
            }

            $from = $tx->from_account_id ? $accounts[$tx->from_account_id] : null;
            $to = $tx->to_account_id ? $accounts[$tx->to_account_id] : null;

            try {
                $this->assertUsable($from, $to);
            } catch (TransferException $e) {
                $this->fail($tx, $from, $e->getMessage(), $adminId);

                return $tx->refresh();
            }

            $this->post($tx, $accounts, reserved: $from !== null, actorId: $adminId);

            return $tx->refresh();
        });
    }

    /** Reject a pending or held transaction. A reason code and text are required. The reserve is released. */
    public function reject(Transaction $transaction, User|int $admin, string $reasonCode, string $reasonText): Transaction
    {
        return $this->tellMember($this->rejectInner($transaction, $admin, $reasonCode, $reasonText));
    }

    private function rejectInner(Transaction $transaction, User|int $admin, string $reasonCode, string $reasonText): Transaction
    {
        $adminId = $admin instanceof User ? $admin->id : $admin;
        $this->assertReason($reasonCode, $reasonText);

        return $this->atomic(function () use ($transaction, $adminId, $reasonCode, $reasonText) {
            $accounts = $this->lockForTransaction($transaction);
            $tx = $this->lockOpen($transaction);

            Decision::create([
                'decidable_type' => $tx->getMorphClass(), 'decidable_id' => $tx->id,
                'decision' => 'rejected', 'reason_code' => $reasonCode, 'reason_text' => $reasonText, 'decided_by' => $adminId,
            ]);

            if ($tx->from_account_id) {
                $this->releaseReserve($accounts[$tx->from_account_id], $tx->totalDebit());
            }

            $tx->update(['status' => TransactionStatus::Rejected]);
            Audit::record('transaction.rejected', $tx, ['status' => 'open'], ['status' => 'rejected', 'reason' => $reasonCode], $adminId);

            return $tx->refresh();
        });
    }

    /** Hold a transaction for review with a reason and an optional expected release date. The reserve stays. */
    public function hold(Transaction $transaction, User|int $admin, string $reasonCode, string $reasonText, ?CarbonInterface $until = null): Transaction
    {
        return $this->tellMember($this->holdInner($transaction, $admin, $reasonCode, $reasonText, $until));
    }

    private function holdInner(Transaction $transaction, User|int $admin, string $reasonCode, string $reasonText, ?CarbonInterface $until = null): Transaction
    {
        $adminId = $admin instanceof User ? $admin->id : $admin;
        $this->assertReason($reasonCode, $reasonText);

        return $this->atomic(function () use ($transaction, $adminId, $reasonCode, $reasonText, $until) {
            $this->lockForTransaction($transaction);
            $tx = $this->lockOpen($transaction);

            Decision::create([
                'decidable_type' => $tx->getMorphClass(), 'decidable_id' => $tx->id,
                'decision' => 'held', 'reason_code' => $reasonCode, 'reason_text' => $reasonText,
                'decided_by' => $adminId, 'hold_until' => $until,
            ]);

            $tx->update(['status' => TransactionStatus::Held]);
            Audit::record('transaction.held', $tx, null, ['reason' => $reasonCode, 'until' => $until?->toIso8601String()], $adminId);

            return $tx->refresh();
        });
    }

    /** Take a held transaction back to pending so it can be approved or rejected. */
    public function release(Transaction $transaction, User|int $admin, ?string $note = null): Transaction
    {
        $adminId = $admin instanceof User ? $admin->id : $admin;

        return $this->atomic(function () use ($transaction, $adminId, $note) {
            $this->lockForTransaction($transaction);
            $tx = Transaction::lockForUpdate()->findOrFail($transaction->id);

            if ($tx->status !== TransactionStatus::Held) {
                throw TransferException::because('invalid_state', 'Only a held transaction can be released.');
            }

            Decision::create([
                'decidable_type' => $tx->getMorphClass(), 'decidable_id' => $tx->id,
                'decision' => 'released', 'reason_text' => $note, 'decided_by' => $adminId,
            ]);

            $tx->update(['status' => TransactionStatus::Pending]);
            Audit::record('transaction.released', $tx, ['status' => 'held'], ['status' => 'pending'], $adminId);

            return $tx->refresh();
        });
    }

    /**
     * Undo a completed transaction with a new reversal transaction. The original is never edited
     * except to be marked reversed. Fees are refunded separately by an adjustment.
     */
    public function reverse(Transaction $transaction, User|int $admin, string $reasonCode, string $reasonText): Transaction
    {
        $adminId = $admin instanceof User ? $admin->id : $admin;
        $this->assertReason($reasonCode, $reasonText);

        return $this->atomic(function () use ($transaction, $adminId, $reasonCode, $reasonText) {
            $accounts = $this->lockForTransaction($transaction);
            $original = Transaction::lockForUpdate()->findOrFail($transaction->id);

            if ($original->status !== TransactionStatus::Completed) {
                throw TransferException::because('invalid_state', 'Only a completed transaction can be reversed.');
            }

            $from = $original->to_account_id ? $accounts[$original->to_account_id] : null;
            $to = $original->from_account_id ? $accounts[$original->from_account_id] : null;
            $this->assertUsable($from, $to);

            $reversal = Transaction::create([
                'reference' => (string) Str::ulid(),
                'idempotency_key' => 'reverse:'.$original->reference,
                'type' => TransactionType::Reversal,
                'from_account_id' => $from?->id,
                'to_account_id' => $to?->id,
                'amount' => $original->amount,
                'fee' => 0,
                'status' => TransactionStatus::Pending,
                'initiated_by' => $adminId,
                'related_transaction_id' => $original->id,
                'effective_date' => now()->toDateString(),
                'memo' => 'Reversal: '.$reasonText,
                'member_description' => 'Reversal of '.($original->member_description ?? $original->type->value),
            ]);

            $this->post($reversal, $accounts, reserved: false, actorId: $adminId);
            $original->update(['status' => TransactionStatus::Reversed]);
            Audit::record('transaction.reversed', $original, ['status' => 'completed'], ['status' => 'reversed', 'reason' => $reasonCode, 'reversal' => $reversal->reference], $adminId);

            return $reversal->refresh();
        });
    }

    /**
     * One database transaction for a whole operation. SQLite has no row locks, so the first
     * statement takes its single write lock up front. Otherwise two requests could both read
     * the same balance and then fight over who writes. MySQL and PostgreSQL use lockForUpdate().
     */
    private function atomic(callable $work): mixed
    {
        return DB::transaction(function () use ($work) {
            if (DB::connection()->getDriverName() === 'sqlite') {
                DB::statement('UPDATE accounts SET id = id WHERE 0 = 1');
            }

            return $work();
        });
    }

    // ---------------------------------------------------------------- rules

    private function assertShape(TransferRequest $r): void
    {
        if ($r->amount->cents <= 0 || $r->feeOrZero()->cents < 0) {
            throw TransferException::because('invalid_amount', 'The amount must be greater than zero.');
        }
        if ($r->fromAccountId === null && $r->toAccountId === null) {
            throw TransferException::because('invalid_accounts', 'A transaction needs at least one account.');
        }
        if ($r->fromAccountId !== null && $r->fromAccountId === $r->toAccountId) {
            throw TransferException::because('invalid_accounts', 'Choose two different accounts.');
        }
        if ($r->type->isInternal() && ($r->fromAccountId === null || $r->toAccountId === null)) {
            throw TransferException::because('invalid_accounts', 'This transaction needs both a sending and a receiving account.');
        }
        if (trim($r->idempotencyKey) === '') {
            throw TransferException::because('invalid_key', 'An idempotency key is required.');
        }
    }

    /** Lock the accounts involved, including the linked overdraft account, in ascending id order. */
    private function lockAccounts(TransferRequest $r): Collection
    {
        $ids = collect([$r->fromAccountId, $r->toAccountId])->filter();

        if ($r->fromAccountId && ($linked = Account::whereKey($r->fromAccountId)->value('overdraft_account_id'))) {
            $ids->push($linked);
        }

        return $this->lockIds($ids);
    }

    private function lockForTransaction(Transaction $t): Collection
    {
        $ids = collect([$t->from_account_id, $t->to_account_id])->filter();

        return $this->lockIds($ids);
    }

    private function lockIds(Collection $ids): Collection
    {
        $ids = $ids->unique()->sort()->values();
        $accounts = Account::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

        if ($accounts->count() !== $ids->count()) {
            throw TransferException::because('unknown_account', 'One of the accounts could not be found.');
        }

        return $accounts;
    }

    private function lockOpen(Transaction $t): Transaction
    {
        $tx = Transaction::lockForUpdate()->findOrFail($t->id);

        if (! $tx->status->isOpen()) {
            throw TransferException::because('invalid_state', 'This transaction has already been decided.');
        }

        return $tx;
    }

    private function assertSameRequest(Transaction $existing, TransferRequest $r): void
    {
        $same = $existing->type === $r->type
            && $existing->from_account_id === $r->fromAccountId
            && $existing->to_account_id === $r->toAccountId
            && $existing->amount->cents === $r->amount->cents;

        if (! $same) {
            throw TransferException::because('idempotency_conflict', 'That request key was already used for a different transaction.');
        }
    }

    /** Accounts and their holders must be active and no restriction may block the direction of the money. */
    private function assertUsable(?Account $from, ?Account $to): void
    {
        foreach ([[$from, 'debit'], [$to, 'credit']] as [$account, $direction]) {
            if (! $account) {
                continue;
            }
            if ($account->status !== 'active') {
                throw TransferException::because('account_inactive', 'This account is not open.');
            }

            $userIds = AccountHolder::where('account_id', $account->id)->where('status', 'active')->pluck('user_id');

            foreach (User::whereIn('id', $userIds)->get(['id', 'status', 'locked_until']) as $holder) {
                if ($holder->status !== 'active' || ($holder->locked_until && $holder->locked_until->isFuture())) {
                    throw TransferException::because('member_locked', 'This membership is restricted. Please contact Customer Care.');
                }
            }

            $blocked = AccountRestriction::active()
                ->where(fn ($q) => $q->where('account_id', $account->id)->orWhereIn('user_id', $userIds))
                ->pluck('type');

            $blocks = $direction === 'debit'
                ? $blocked->contains(AccountRestriction::FULL_LOCK) || $blocked->contains(AccountRestriction::DEBIT_BLOCK)
                : $blocked->contains(AccountRestriction::FULL_LOCK) || $blocked->contains(AccountRestriction::CREDIT_BLOCK);

            if ($blocks) {
                throw TransferException::because('account_locked', $direction === 'debit'
                    ? 'Money cannot be taken from this account right now. Please contact Customer Care.'
                    : 'This account cannot receive money right now.');
            }
        }
    }

    private function assertLimits(TransferRequest $r, Account $from): void
    {
        $need = $r->amount->add($r->feeOrZero());

        if ($need->cents > config('bank.per_transaction_limit')) {
            throw TransferException::because('limit_exceeded', 'This amount is over the limit for a single transaction.');
        }

        $limit = $from->daily_limit?->cents ?? config('bank.default_daily_limit');
        $query = Transaction::where('from_account_id', $from->id)
            ->whereIn('type', array_map(fn (TransactionType $t) => $t->value, array_filter(TransactionType::cases(), fn ($t) => $t->isLimited())))
            ->whereIn('status', [TransactionStatus::Pending->value, TransactionStatus::Held->value, TransactionStatus::Completed->value])
            ->whereDate('effective_date', $r->effectiveDate ?? now()->toDateString());
        $used = (int) $query->sum('amount') + (int) $query->sum('fee');

        if ($used + $need->cents > $limit) {
            throw TransferException::because('daily_limit_exceeded', 'This would go over the daily limit for this account.');
        }
    }

    private function needsReview(TransferRequest $r): bool
    {
        // A Super Admin never waits: not for the review threshold, a wire, or a check deposit, whether acting as themselves
        // or as a member. The transaction is still recorded in the ledger and the audit log in full.
        if (StaffDirectory::isSuperAdmin($r->initiatedBy) || StaffDirectory::isSuperAdmin($r->impersonatorId)) {
            return false;
        }

        if ($r->requireReview || $r->type->alwaysNeedsReview()) {
            return true;
        }

        return ($r->type->isLimited() || $r->type === TransactionType::Adjustment)
            && $r->amount->cents >= config('bank.review_threshold');
    }

    // ---------------------------------------------------------------- overdraft

    /** Move the shortfall from the linked savings account first, as its own transaction. */
    private function coverShortfall(Account $from, Collection $accounts, Money $need, TransferRequest $r): void
    {
        $linked = $from->overdraft_account_id ? $accounts[$from->overdraft_account_id] : null;

        if (! $linked) {
            throw TransferException::because('insufficient_funds', 'There is not enough available money in this account.');
        }

        $fee = Money::cents((int) config('bank.overdraft_transfer_fee'));
        $shortfall = $need->subtract($from->available_balance)->add($fee);

        if ($linked->available_balance->lessThan($shortfall)) {
            throw TransferException::because('insufficient_funds', 'There is not enough available money in this account or its linked savings.');
        }

        $this->assertUsable($linked, null);

        $transfer = $this->createTransaction(new TransferRequest(
            type: TransactionType::OverdraftTransfer,
            amount: $shortfall,
            idempotencyKey: $r->idempotencyKey.':overdraft',
            fromAccountId: $linked->id,
            toAccountId: $from->id,
            initiatedBy: $r->initiatedBy,
            impersonatorId: $r->impersonatorId,
            memberDescription: 'Overdraft protection transfer',
        ));
        $this->post($transfer, $accounts, reserved: false);

        if ($fee->isPositive()) {
            $feeTx = $this->createTransaction(new TransferRequest(
                type: TransactionType::Fee,
                amount: $fee,
                idempotencyKey: $r->idempotencyKey.':overdraft-fee',
                fromAccountId: $from->id,
                initiatedBy: $r->initiatedBy,
                impersonatorId: $r->impersonatorId,
                memberDescription: 'Overdraft protection transfer fee',
            ));
            $this->post($feeTx, $accounts, reserved: false);
        }
    }

    // ---------------------------------------------------------------- posting

    private function createTransaction(TransferRequest $r): Transaction
    {
        return Transaction::create([
            'reference' => (string) Str::ulid(),
            'idempotency_key' => $r->idempotencyKey,
            'type' => $r->type,
            'from_account_id' => $r->fromAccountId,
            'to_account_id' => $r->toAccountId,
            'payee_id' => $r->payeeId,
            'amount' => $r->amount,
            'fee' => $r->feeOrZero(),
            'status' => TransactionStatus::Pending,
            'initiated_by' => $r->initiatedBy,
            'impersonator_id' => $r->impersonatorId,
            'effective_date' => $r->effectiveDate ?? now()->toDateString(),
            'memo' => $r->memo,
            'member_description' => $r->memberDescription,
            'category_id' => $r->categoryId,
        ]);
    }

    /**
     * Write the ledger entries and update balances. Accounts must already be locked.
     * With `reserved` true, the amount was set aside in held_balance when the request was made.
     */
    private function post(Transaction $tx, Collection $accounts, bool $reserved, ?int $actorId = null): void
    {
        if ($tx->from_account_id) {
            $from = $accounts[$tx->from_account_id];
            $need = $tx->totalDebit();
            $held = $from->held_balance->cents - ($reserved ? $need->cents : 0);

            if ($from->balance->cents - $held < $need->cents) {
                throw TransferException::because('insufficient_funds', 'There is not enough available money in this account.');
            }

            $balance = $from->balance->cents - $tx->amount->cents;
            $this->entry($tx, $from, 'debit', $tx->amount->cents, $balance);

            if ($tx->fee->isPositive()) {
                $balance -= $tx->fee->cents;
                $this->entry($tx, $from, 'debit', $tx->fee->cents, $balance);
            }

            $from->balance = $balance;
            $from->held_balance = $held;
            $from->available_balance = $balance - $held;
            $from->save();
        }

        if ($tx->to_account_id) {
            $to = $accounts[$tx->to_account_id];
            $balance = $to->balance->cents + $tx->amount->cents;
            $this->entry($tx, $to, 'credit', $tx->amount->cents, $balance);

            $to->balance = $balance;
            $to->available_balance = $balance - $to->held_balance->cents;
            $to->save();
        }

        $tx->update(['status' => TransactionStatus::Completed, 'posted_at' => now()]);
        Audit::record('transaction.posted', $tx, null, ['type' => $tx->type->value, 'amount' => $tx->amount->cents, 'fee' => $tx->fee->cents], $actorId ?? $tx->initiated_by, $tx->impersonator_id);
    }

    private function entry(Transaction $tx, Account $account, string $direction, int $amount, int $balanceAfter): void
    {
        LedgerEntry::create([
            'transaction_id' => $tx->id,
            'account_id' => $account->id,
            'direction' => $direction,
            'amount' => $amount,
            'balance_after' => $balanceAfter,
        ]);
    }

    private function reserve(Account $account, Money $amount): void
    {
        $account->held_balance = $account->held_balance->add($amount);
        $account->available_balance = $account->balance->subtract($account->held_balance);
        $account->save();
    }

    private function releaseReserve(Account $account, Money $amount): void
    {
        $account->held_balance = $account->held_balance->subtract($amount);
        $account->available_balance = $account->balance->subtract($account->held_balance);
        $account->save();
    }

    private function fail(Transaction $tx, ?Account $from, string $why, int $actorId): void
    {
        if ($from) {
            $this->releaseReserve($from, $tx->totalDebit());
        }
        $tx->update(['status' => TransactionStatus::Failed]);
        Audit::record('transaction.failed', $tx, null, ['why' => $why], $actorId);
    }

    private function assertReason(string $code, string $text): void
    {
        if (trim($text) === '') {
            throw TransferException::because('reason_required', 'A reason is required.');
        }
        if (! ReasonCode::where('code', $code)->exists()) {
            throw TransferException::because('reason_required', 'Choose a valid reason code.');
        }
    }

    /** One short email when staff decide on a request. No amounts and no reasons travel by email: the member signs in for those. */
    private function tellMember(Transaction $tx): Transaction
    {
        $label = $tx->type->memberLabel();
        $phone = brand('support_phone');
        $notice = match ($tx->status) {
            TransactionStatus::Completed => ['Your '.$label.' was approved', ['We approved your '.$label.'.', 'Sign in to online banking to see it in your activity.']],
            TransactionStatus::Rejected, TransactionStatus::Failed => ['We could not complete your '.$label, ['We were not able to complete your '.$label.'.', 'Sign in to online banking for details, or call Customer Care at '.$phone.'.']],
            TransactionStatus::Held => ['We are reviewing your '.$label, ['Our team is reviewing your '.$label.'. This sometimes takes a little longer.', 'You do not need to do anything. We will email you when there is a decision.']],
            default => null,
        };
        if ($notice === null) {
            return $tx;
        }
        $account = $tx->fromAccount ?: $tx->toAccount;
        foreach ($account?->users ?? [] as $holder) {
            \App\Notifications\MemberNotice::send($holder, $notice[0], $notice[1], 'Open online banking', route('activity'));
        }

        return $tx;
    }
}
