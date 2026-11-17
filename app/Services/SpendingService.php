<?php

namespace App\Services;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Budget;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Spending insights: where a member's money went in a month, income against spending, and budgets. */
final class SpendingService
{
    /** Money going out that counts as spending. Moving money between accounts or banks does not. */
    private const SPEND_TYPES = [TransactionType::CardPurchase, TransactionType::BillPayment, TransactionType::Withdrawal, TransactionType::Fee, TransactionType::LoanPayment];
    private const INCOME_TYPES = [TransactionType::Deposit, TransactionType::Dividend];

    public function categoryFor(Transaction $tx, array $overrides = []): string
    {
        if (isset($overrides[$tx->id])) {
            return $overrides[$tx->id];
        }

        return match ($tx->type) {
            TransactionType::LoanPayment => 'loans',
            TransactionType::Withdrawal, TransactionType::Fee => 'cash_fees',
            TransactionType::BillPayment => 'bills',
            default => $this->byMerchant((string) $tx->member_description),
        };
    }

    private function byMerchant(string $name): string
    {
        $name = strtolower($name);
        foreach (config('spending.merchant_words') as $cat => $words) {
            foreach ($words as $w) {
                if (str_contains($name, $w)) {
                    return $cat;
                }
            }
        }

        return 'other';
    }

    /** @return array{spend: array<int, array{tx: Transaction, category: string}>, income: int} */
    public function month(User $user, Carbon $month): array
    {
        $ids = $user->accounts()->where('accounts.type', 'standard')->pluck('accounts.id');
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();
        $base = Transaction::where('status', TransactionStatus::Completed->value)->whereBetween('effective_date', [$start->toDateString(), $end->toDateString()]);

        $overrides = DB::table('transaction_categories')->where('user_id', $user->id)->pluck('category', 'transaction_id')->all();
        $spend = (clone $base)->whereIn('from_account_id', $ids)->whereIn('type', array_map(fn ($t) => $t->value, self::SPEND_TYPES))->orderByDesc('effective_date')->get()
            ->map(fn ($tx) => ['tx' => $tx, 'category' => $this->categoryFor($tx, $overrides)])->all();
        $income = (int) (clone $base)->whereIn('to_account_id', $ids)->whereIn('type', array_map(fn ($t) => $t->value, self::INCOME_TYPES))->sum('amount');

        return ['spend' => $spend, 'income' => $income];
    }

    /** @return array<string, array{total:int,count:int}> */
    public function byCategory(array $spend): array
    {
        $out = [];
        foreach ($spend as $row) {
            $c = $row['category'];
            $out[$c] ??= ['total' => 0, 'count' => 0];
            $out[$c]['total'] += $row['tx']->amount->cents + $row['tx']->fee->cents;
            $out[$c]['count']++;
        }
        uasort($out, fn ($a, $b) => $b['total'] <=> $a['total']);

        return $out;
    }

    /** @return array<string, int> merchant => cents, top five */
    public function topMerchants(array $spend): array
    {
        $out = [];
        foreach ($spend as $row) {
            if ($row['tx']->type !== TransactionType::CardPurchase && $row['tx']->type !== TransactionType::BillPayment) {
                continue;
            }
            $name = (string) $row['tx']->member_description ?: 'Other';
            $out[$name] = ($out[$name] ?? 0) + $row['tx']->amount->cents;
        }
        arsort($out);

        return array_slice($out, 0, 5, true);
    }

    public function setCategory(User $user, Transaction $tx, string $category): void
    {
        if (! isset(config('spending.categories')[$category])) {
            throw ValidationException::withMessages(['category' => 'Choose a category.']);
        }
        $ids = $user->accounts()->pluck('accounts.id');
        abort_unless($ids->contains($tx->from_account_id), 404);
        DB::table('transaction_categories')->updateOrInsert(['user_id' => $user->id, 'transaction_id' => $tx->id], ['category' => $category, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function setBudget(User $user, string $category, ?int $limitCents): void
    {
        if (! isset(config('spending.categories')[$category])) {
            throw ValidationException::withMessages(['category' => 'Choose a category.']);
        }
        if ($limitCents === null || $limitCents <= 0) {
            Budget::where('user_id', $user->id)->where('category', $category)->delete();
        } else {
            Budget::updateOrCreate(['user_id' => $user->id, 'category' => $category], ['monthly_limit' => $limitCents]);
        }
        Audit::record('budget.set', $user, null, ['category' => $category, 'limit' => $limitCents], $user->id);
    }

    /** Each budget with how much is spent this month and whether it is near or over. @return list<array> */
    public function budgetStatus(User $user, ?Carbon $month = null): array
    {
        $byCat = $this->byCategory($this->month($user, $month ?? today())['spend']);
        $warn = (int) config('bank.budget_warn_percent');

        return Budget::where('user_id', $user->id)->get()->map(function (Budget $b) use ($byCat, $warn) {
            $spent = $byCat[$b->category]['total'] ?? 0;
            $pct = (int) floor($spent * 100 / max(1, $b->monthly_limit));

            return ['category' => $b->category, 'limit' => $b->monthly_limit, 'spent' => $spent, 'percent' => $pct, 'state' => $pct >= 100 ? 'over' : ($pct >= $warn ? 'near' : 'ok')];
        })->sortByDesc('percent')->values()->all();
    }

    /** Monthly job: store each member's totals for the month so past months open fast. Returns rows written. */
    public function summarize(Carbon $month): int
    {
        $rows = 0;
        $key = $month->format('Y-m');
        foreach (User::where('role', 'user')->get() as $user) {
            foreach ($this->byCategory($this->month($user, $month)['spend']) as $cat => $v) {
                DB::table('spending_summaries')->updateOrInsert(['user_id' => $user->id, 'month' => $key, 'category' => $cat], ['total' => $v['total'], 'count' => $v['count'], 'created_at' => now(), 'updated_at' => now()]);
                $rows++;
            }
        }

        return $rows;
    }
}
