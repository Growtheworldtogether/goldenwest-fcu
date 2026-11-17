<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payee;
use App\Models\User;
use App\Services\DepositService;
use App\Services\Ledger\TransferException;
use App\Services\PayeeService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class DepositController extends Controller
{
    /** A teller records cash handed over at the counter. */
    public function cash(Request $request, User $user, DepositService $deposits)
    {
        Gate::authorize('staff.postDeposits');
        abort_if($user->isAdmin(), 404);
        $data = $request->validate([
            'account_id' => ['required', 'integer'],
            'amount' => ['required', 'string', 'max:20'],
            'note' => ['nullable', 'string', 'max:200'],
            'token' => ['required', 'regex:/^[0-9A-HJKMNP-TV-Z]{26}$/'],
        ]);
        $account = $user->accounts()->where('accounts.id', $data['account_id'])->firstOrFail();

        try {
            $amount = Money::parse($data['amount']);
            $deposit = $deposits->cash($request->user(), $account, $amount, $data['note'] ?? null, $data['token']);
        } catch (InvalidArgumentException) {
            return back()->withInput()->withErrors(['amount' => 'Enter an amount such as 250.00.']);
        } catch (TransferException $e) {
            return back()->withInput()->withErrors(['amount' => $e->getMessage()]);
        }

        return back()->with('toast', $deposit->transaction->status->value === 'completed'
            ? ['type' => 'ok', 'message' => 'Cash deposit of '.$amount->format().' posted.']
            : ['type' => 'info', 'message' => 'Cash deposit of '.$amount->format().' is waiting for a second person to approve it.']);
    }

    public function waive(Request $request, User $user, Payee $payee, PayeeService $payees)
    {
        Gate::authorize('staff.waivePayeeWait');
        abort_unless($payee->user_id === $user->id && $payee->status === 'active', 404);
        $payees->waiveWait($payee, $request->user());

        return back()->with('status', 'The waiting period for '.$payee->label().' is over. The member can use it now.');
    }
}
