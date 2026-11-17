<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Goal;
use App\Services\GoalService;
use App\Services\Ledger\TransferException;
use App\Support\Money;
use Illuminate\Http\Request;
use InvalidArgumentException;

class GoalController extends Controller
{
    private function mine(Request $r, Goal $g): Goal
    {
        abort_unless($g->user_id === $r->user()->id, 404);

        return $g;
    }

    private function cents(mixed $v): int
    {
        return (int) round(((float) $v) * 100);
    }

    public function index(Request $request)
    {
        $u = $request->user();

        return view('portal.goals', [
            'goals' => Goal::with(['account', 'parent'])->where('user_id', $u->id)->where('status', '!=', 'closed')->orderBy('id')->get(),
            'accounts' => $u->accounts()->with('product')->where('accounts.status', 'active')->where('accounts.type', 'standard')->get(),
        ]);
    }

    public function store(Request $request, GoalService $goals)
    {
        $d = $request->validate([
            'name' => ['required', 'string', 'max:60'], 'target' => ['required', 'numeric', 'min:1', 'max:10000000'],
            'target_date' => ['nullable', 'date', 'after:today'], 'parent_account_id' => ['required', 'integer', 'exists:accounts,id'],
        ], ['name.required' => 'Give your goal a name.', 'target.required' => 'Set a target amount.', 'target_date.after' => 'Pick a date in the future.']);
        $goals->create($request->user(), \App\Models\Account::findOrFail($d['parent_account_id']), $d['name'], $this->cents($d['target']), $d['target_date'] ?? null);

        return back()->with('toast', ['type' => 'ok', 'message' => 'Goal created. Start saving whenever you like.']);
    }

    public function contribute(Request $request, Goal $goal, GoalService $goals)
    {
        $d = $request->validate(['amount' => ['required', 'numeric', 'min:0.01'], 'direction' => ['required', 'in:in,out']]);
        $this->mine($request, $goal);
        try {
            $d['direction'] === 'in' ? $goals->contribute($goal, Money::cents($this->cents($d['amount']))) : $goals->withdraw($goal, Money::cents($this->cents($d['amount'])));
        } catch (TransferException|InvalidArgumentException $e) {
            return back()->with('toast', ['type' => 'bad', 'message' => $e->getMessage()]);
        }

        return back()->with('toast', ['type' => 'ok', 'message' => $d['direction'] === 'in' ? 'Saved toward your goal.' : 'Moved back to your account.']);
    }

    public function auto(Request $request, Goal $goal, GoalService $goals)
    {
        $d = $request->validate(['auto_amount' => ['nullable', 'numeric', 'min:0'], 'auto_frequency' => ['nullable', 'in:weekly,monthly,']]);
        $off = ! $request->filled('auto_amount') || ! $request->filled('auto_frequency');
        $goals->setAuto($this->mine($request, $goal), $off ? null : $this->cents($d['auto_amount']), $off ? null : $d['auto_frequency']);

        return back()->with('toast', ['type' => 'ok', 'message' => $off ? 'Automatic saving is off.' : 'Automatic saving is on.']);
    }

    public function close(Request $request, Goal $goal, GoalService $goals)
    {
        try {
            $goals->close($this->mine($request, $goal));
        } catch (TransferException|InvalidArgumentException $e) {
            return back()->with('toast', ['type' => 'bad', 'message' => $e->getMessage()]);
        }

        return back()->with('toast', ['type' => 'info', 'message' => 'Goal closed. Your savings went back to the account.']);
    }
}
