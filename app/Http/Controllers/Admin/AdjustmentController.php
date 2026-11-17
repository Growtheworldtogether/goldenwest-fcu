<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit;
use App\Services\Ledger\TransferException;
use App\Services\Ledger\TransferRequest;
use App\Services\Ledger\TransferService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class AdjustmentController extends Controller
{
    public function store(Request $request, User $user, TransferService $ledger)
    {
        Gate::authorize('staff.canAdjust');
        $data = $request->validate([
            'account_id' => ['required', 'integer'],
            'direction' => ['required', Rule::in(['credit', 'debit'])],
            'amount' => ['required', 'string', 'max:20'],
            'description' => ['required', 'string', 'max:140'],
            'reason_code' => ['required', 'exists:reason_codes,code'],
            'note' => ['required', 'string', 'max:1000'],
            'token' => ['required', 'regex:/^[0-9A-HJKMNP-TV-Z]{26}$/'],
        ]);

        $account = $user->accounts()->where('accounts.id', $data['account_id'])->firstOrFail();

        try {
            $amount = Money::parse($data['amount']);
        } catch (InvalidArgumentException) {
            return back()->withInput()->withErrors(['amount' => 'Enter an amount such as 25.00.']);
        }
        Gate::authorize('staff.adjustBalance', $amount->cents);

        try {
            $tx = $ledger->execute(new TransferRequest(
                type: TransactionType::Adjustment,
                amount: $amount,
                idempotencyKey: 'adjust:'.$request->user()->id.':'.$data['token'],
                fromAccountId: $data['direction'] === 'debit' ? $account->id : null,
                toAccountId: $data['direction'] === 'credit' ? $account->id : null,
                initiatedBy: $request->user()->id,
                memo: '['.$data['reason_code'].'] '.$data['note'],
                memberDescription: $data['description'],
            ));
        } catch (TransferException $e) {
            return back()->withInput()->withErrors(['amount' => $e->getMessage()]);
        }
        Audit::record('adjustment.requested', $tx, null, ['direction' => $data['direction'], 'reason' => $data['reason_code'], 'note' => $data['note']], $request->user()->id);

        return back()->with('status', $tx->status === TransactionStatus::Completed
            ? 'Adjustment posted. The member sees it on their statement.'
            : 'Adjustment is waiting for approval by another staff member. The amount is set aside.');
    }
}
