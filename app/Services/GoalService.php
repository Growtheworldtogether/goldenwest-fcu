<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\AccountHolder;
use App\Models\Goal;
use App\Models\Product;
use App\Models\User;
use App\Services\Ledger\TransferException;
use App\Services\Ledger\TransferRequest;
use App\Services\Ledger\TransferService;
use App\Support\AccountNumber;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Savings goals. Each goal is a hidden sub-account of a parent account, so saving toward it is an
 * ordinary transfer that shows in the parent's ledger and no balance code needs a special case.
 */
final class GoalService
{
    public function __construct(private TransferService $ledger)
    {
    }

    public function create(User $member, Account $parent, string $name, int $targetCents, ?string $targetDate): Goal
    {
        if (! $member->accounts()->where('accounts.id', $parent->id)->where('accounts.status', 'active')->where('accounts.type', 'standard')->exists()) {
            throw ValidationException::withMessages(['parent_account_id' => 'Choose one of your accounts.']);
        }
        if ($targetCents < 100) {
            throw ValidationException::withMessages(['target' => 'Set a target of at least $1.']);
        }
        if (Goal::where('user_id', $member->id)->where('status', 'active')->count() >= 10) {
            throw ValidationException::withMessages(['name' => 'You can have up to 10 goals at a time.']);
        }

        return DB::transaction(function () use ($member, $parent, $name, $targetCents, $targetDate) {
            $account = Account::create([
                'product_id' => Product::where('kind', 'share_savings')->value('id') ?? $parent->product_id, 'type' => 'goal', 'parent_account_id' => $parent->id,
                'account_number' => AccountNumber::account(), 'nickname' => $name,
            ]);
            AccountHolder::create(['account_id' => $account->id, 'user_id' => $member->id, 'role' => 'primary']);
            $goal = Goal::create(['user_id' => $member->id, 'parent_account_id' => $parent->id, 'account_id' => $account->id, 'name' => $name, 'target_amount' => $targetCents, 'target_date' => $targetDate]);
            Audit::record('goal.created', $goal, null, ['name' => $name, 'target' => $targetCents], $member->id);

            return $goal;
        });
    }

    public function contribute(Goal $goal, Money $amount, ?int $by = null, ?string $key = null): void
    {
        $this->assertActive($goal);
        $this->ledger->execute(new TransferRequest(
            type: TransactionType::Transfer, amount: $amount, idempotencyKey: $key ?? 'goal-in-'.$goal->id.'-'.bin2hex(random_bytes(6)),
            fromAccountId: $goal->parent_account_id, toAccountId: $goal->account_id, initiatedBy: $by ?? $goal->user_id, memberDescription: 'Saved to goal: '.$goal->name,
        ));
        $this->refreshStatus($goal);
        Audit::record('goal.contribution', $goal, null, ['amount' => $amount->cents], $by ?? $goal->user_id);
    }

    public function withdraw(Goal $goal, Money $amount): void
    {
        if ($goal->status === 'closed') {
            throw new InvalidArgumentException('This goal is closed.');
        }
        $this->ledger->execute(new TransferRequest(
            type: TransactionType::Transfer, amount: $amount, idempotencyKey: 'goal-out-'.$goal->id.'-'.bin2hex(random_bytes(6)),
            fromAccountId: $goal->account_id, toAccountId: $goal->parent_account_id, initiatedBy: $goal->user_id, memberDescription: 'Taken from goal: '.$goal->name,
        ));
        $this->refreshStatus($goal);
        Audit::record('goal.withdrawal', $goal, null, ['amount' => $amount->cents], $goal->user_id);
    }

    /** Close a goal: whatever is saved goes back to the parent account. */
    public function close(Goal $goal): void
    {
        $this->assertActive($goal);
        DB::transaction(function () use ($goal) {
            $balance = $goal->account->refresh()->balance;
            if ($balance->isPositive()) {
                $this->withdraw($goal, $balance);
            }
            $goal->update(['status' => 'closed', 'auto_amount' => null, 'auto_frequency' => null, 'auto_next_date' => null]);
            $goal->account->update(['status' => 'closed']);
            Audit::record('goal.closed', $goal, null, null, $goal->user_id);
        });
    }

    public function setAuto(Goal $goal, ?int $amountCents, ?string $frequency): void
    {
        $this->assertActive($goal);
        if ($amountCents === null || $frequency === null || $frequency === '') {
            $goal->update(['auto_amount' => null, 'auto_frequency' => null, 'auto_next_date' => null]);
        } else {
            if (! in_array($frequency, ['weekly', 'monthly'], true) || $amountCents < 100) {
                throw ValidationException::withMessages(['auto_amount' => 'Choose at least $1, weekly or monthly.']);
            }
            $goal->update(['auto_amount' => $amountCents, 'auto_frequency' => $frequency, 'auto_next_date' => today()->addDays($frequency === 'weekly' ? 7 : 30)]);
        }
        Audit::record('goal.auto_changed', $goal, null, ['amount' => $amountCents, 'frequency' => $frequency], $goal->user_id);
    }

    /** Daily job: make each automatic contribution that is due. Returns how many were made. */
    public function runAuto(): int
    {
        $made = 0;
        foreach (Goal::where('status', 'active')->whereNotNull('auto_amount')->whereDate('auto_next_date', '<=', today())->get() as $goal) {
            try {
                $this->contribute($goal, Money::cents($goal->auto_amount), null, 'goal-auto-'.$goal->id.'-'.$goal->auto_next_date->toDateString());
                $made++;
            } catch (TransferException $e) {
                Audit::record('goal.auto_failed', $goal, null, ['why' => $e->getMessage()], null);
            }
            $next = $goal->auto_frequency === 'weekly' ? $goal->auto_next_date->copy()->addDays(7) : $goal->auto_next_date->copy()->addMonthNoOverflow();
            $goal->update(['auto_next_date' => $next->lt(today()) ? today()->addDay() : $next]);
        }

        return $made;
    }

    private function refreshStatus(Goal $goal): void
    {
        $goal->refresh();
        $balance = $goal->account->refresh()->balance->cents;
        $status = $balance >= $goal->target_amount ? 'achieved' : 'active';
        if ($goal->status !== 'closed' && $goal->status !== $status) {
            $goal->update(['status' => $status]);
            if ($status === 'achieved') {
                Audit::record('goal.achieved', $goal, null, null, $goal->user_id);
            }
        }
    }

    private function assertActive(Goal $goal): void
    {
        if ($goal->status === 'closed') {
            throw new InvalidArgumentException('This goal is closed.');
        }
    }
}
