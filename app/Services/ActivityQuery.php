<?php

namespace App\Services;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/** Builds a member's transaction history with filters, shared by the page and the CSV export. */
final class ActivityQuery
{
    public const GROUPS = [
        'deposits' => ['Deposits', [TransactionType::Deposit, TransactionType::Dividend, TransactionType::LoanDisbursement]],
        'purchases' => ['Card purchases', [TransactionType::CardPurchase]],
        'transfers' => ['Transfers', [TransactionType::Transfer, TransactionType::MemberTransfer, TransactionType::OverdraftTransfer, TransactionType::Reversal]],
        'bills' => ['Bills and payments', [TransactionType::BillPayment, TransactionType::AchOut, TransactionType::WireOut]],
        'fees' => ['Fees', [TransactionType::Fee]],
        'adjustments' => ['Adjustments', [TransactionType::Adjustment]],
    ];

    /** @var Collection<int, Account> keyed by id */
    public Collection $accounts;

    public function __construct(public readonly User $user, public readonly ?Account $only = null)
    {
        $this->accounts = $user->accounts()->with('product')->orderBy('accounts.id')->get()->keyBy('id');
    }

    public function query(Request $request): Builder
    {
        $ids = $this->only ? [$this->only->id] : $this->accounts->keys()->all();

        $account = $request->integer('account');
        if (! $this->only && $account && $this->accounts->has($account)) {
            $ids = [$account];
        }

        $q = Transaction::query()
            ->with(['fromAccount', 'toAccount', 'entries'])
            ->where(fn ($w) => $w->whereIn('from_account_id', $ids)->orWhereIn('to_account_id', $ids));

        if ($group = $request->query('type')) {
            if (isset(self::GROUPS[$group])) {
                $q->whereIn('type', array_map(fn ($t) => $t->value, self::GROUPS[$group][1]));
            }
        }

        match ($request->query('direction')) {
            'in' => $q->whereIn('to_account_id', $ids),
            'out' => $q->whereIn('from_account_id', $ids),
            default => null,
        };

        if (($status = $request->query('status')) && TransactionStatus::tryFrom($status)) {
            $q->where('status', $status);
        }
        if ($from = $this->date($request->query('from'))) {
            $q->whereDate('effective_date', '>=', $from);
        }
        if ($to = $this->date($request->query('to'))) {
            $q->whereDate('effective_date', '<=', $to);
        }

        if (($text = trim((string) $request->query('q', ''))) !== '') {
            $q->where(function ($w) use ($text) {
                $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $text).'%';
                $w->where('member_description', 'like', $like)->orWhere('memo', 'like', $like);
                if (preg_match('/^\$?[\d,]+(\.\d{1,2})?$/', $text)) {
                    $w->orWhere('amount', Money::parse($text)->cents);
                }
            });
        }

        return $q->orderByDesc('effective_date')->orderByDesc('id');
    }

    /**
     * One display row per member account on each transaction. A transfer between two of the
     * member's own accounts therefore appears once for each side.
     *
     * @param  iterable<Transaction>  $transactions
     */
    public function rows(iterable $transactions, ?array $limitTo = null): array
    {
        $rows = [];

        foreach ($transactions as $tx) {
            foreach (['from' => $tx->fromAccount, 'to' => $tx->toAccount] as $side => $account) {
                if (! $account || ! $this->accounts->has($account->id)) {
                    continue;
                }
                if ($this->only && $account->id !== $this->only->id) {
                    continue;
                }
                if ($limitTo !== null && ! in_array($account->id, $limitTo, true)) {
                    continue;
                }

                $out = $side === 'from';
                $other = $out ? $tx->toAccount : $tx->fromAccount;
                $entry = $tx->entries->where('account_id', $account->id)->sortBy('id')->last();

                $rows[] = [
                    'tx' => $tx,
                    'account' => $this->accounts[$account->id],
                    'title' => $this->title($tx, $out, $other),
                    'cents' => $out ? -($tx->amount->cents + $tx->fee->cents) : $tx->amount->cents,
                    'balance_after' => $entry && $tx->status === TransactionStatus::Completed ? $entry->balance_after : null,
                ];
            }
        }

        return $rows;
    }

    private function title(Transaction $tx, bool $out, ?Account $other): string
    {
        $isOwn = $other && $this->accounts->has($other->id);

        if ($tx->type === TransactionType::Transfer || $tx->type === TransactionType::OverdraftTransfer) {
            $label = $tx->type === TransactionType::OverdraftTransfer ? 'Overdraft protection transfer' : ($out ? 'Transfer to' : 'Transfer from');
            if ($tx->type === TransactionType::OverdraftTransfer) {
                return $label.($isOwn ? ($out ? ' to ' : ' from ').$other->nickname.' '.$other->masked() : '');
            }

            return $label.' '.($isOwn ? $other->nickname.' '.$other->masked() : 'another account');
        }

        return $tx->member_description ?: str($tx->type->value)->replace('_', ' ')->ucfirst()->toString();
    }

    private function date(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        return $value;
    }
}
