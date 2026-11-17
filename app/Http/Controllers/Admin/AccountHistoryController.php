<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Services\Audit;
use App\Services\HistoryBuilder;
use App\Services\Ledger\TransferException;
use App\Services\Ledger\TransferService;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/** Gives a new, empty account a past: staff pick the balance it should reach and the dates, and the system fills in the activity. */
class AccountHistoryController extends Controller
{
    public const MIN = 5_000;          // $50
    public const MAX = 50_000_000;     // $500,000

    public function store(Request $request, User $user, TransferService $ledger)
    {
        Gate::authorize('staff.canAdjust');
        $data = $request->validate([
            'account_id' => ['required', 'integer'],
            'target' => ['required', 'string', 'max:20'],
            'from' => ['required', 'date', 'before:'.now()->addDay()->toDateString()],
            'to' => ['required', 'date', 'after_or_equal:from', 'before:'.now()->addDay()->toDateString()],
            'backdate_open' => ['nullable', 'boolean'],
            'note' => ['required', 'string', 'max:1000'],
        ], ['to.after_or_equal' => 'The end date cannot be before the start date.', 'to.before' => 'The end date cannot be in the future.', 'from.before' => 'The start date cannot be in the future.']);

        $account = $user->accounts()->with('product')->where('accounts.id', $data['account_id'])->firstOrFail();
        try {
            $target = Money::parse($data['target']);
        } catch (InvalidArgumentException) {
            return back()->withInput()->withErrors(['target' => 'Enter an amount such as 2500.00.']);
        }
        if ($target->cents < self::MIN || $target->cents > self::MAX) {
            return back()->withInput()->withErrors(['target' => 'Choose a balance between '.Money::cents(self::MIN)->format().' and '.Money::cents(self::MAX)->format().'.']);
        }
        Gate::authorize('staff.adjustBalance', $target->cents);
        if ($account->type !== 'standard' || $account->status !== 'active' || LedgerEntry::where('account_id', $account->id)->exists()) {
            return back()->withInput()->withErrors(['account_id' => 'Choose an open account that has no activity yet.']);
        }

        $seed = random_int(1, 2_000_000_000);
        $kind = $account->product->kind === 'share_draft' ? 'checking' : 'savings';
        $from = CarbonImmutable::parse($data['from'], config('app.timezone'));
        $to = CarbonImmutable::parse($data['to'], config('app.timezone'));
        $rows = HistoryBuilder::build($kind, $from, $to, $target->cents, $seed);

        try {
            $ledger->postHistory($account->id, $rows, $request->user());
        } catch (TransferException $e) {
            return back()->withInput()->withErrors(['account_id' => $e->getMessage()]);
        }
        if ($request->boolean('backdate_open')) {
            $account->forceFill(['created_at' => $from->startOfDay()])->saveQuietly();
        }
        Audit::record('account.history_added', $account, null, [
            'target' => $target->cents, 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'count' => count($rows),
            'seed' => $seed, 'backdated_open' => $request->boolean('backdate_open'), 'note' => $data['note'],
        ], $request->user()->id);

        return back()->with('status', count($rows).' transactions were added from '.$from->format('M j, Y').' to '.$to->format('M j, Y').'. The account now has '.$target->format().'.');
    }
}
