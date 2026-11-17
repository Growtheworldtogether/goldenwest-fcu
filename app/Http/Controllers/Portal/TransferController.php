<?php

namespace App\Http\Controllers\Portal;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Services\Ledger\TransferException;
use App\Services\Ledger\TransferRequest;
use App\Services\Ledger\TransferService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;

class TransferController extends Controller
{
    use Concerns\ChecksStaffCode;

    public function create(Request $request)
    {
        $accounts = $request->user()->accounts()->with('product')->where('accounts.status', 'active')->where('accounts.type', 'standard')->orderBy('accounts.id')->get();

        return view('portal.transfer', [
            'accounts' => $accounts,
            'token' => (string) Str::ulid(), // sent back with the form so a double click moves money once
        ]);
    }

    public function store(Request $request, TransferService $ledger)
    {
        $data = $request->validate([
            'from_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'to_account_id' => ['required', 'integer', 'exists:accounts,id', 'different:from_account_id'],
            'amount' => ['required', 'string', 'max:20'],
            'memo' => ['nullable', 'string', 'max:140'],
            'token' => ['required', 'regex:/^[0-9A-HJKMNP-TV-Z]{26}$/'],
        ], ['to_account_id.different' => 'Choose two different accounts.']);

        $acting = $request->session()->get('impersonation');
        if ($problem = $this->staffCodeProblem($request)) {
            return $problem;
        }

        $from = Account::findOrFail($data['from_account_id']);
        $to = Account::findOrFail($data['to_account_id']);
        Gate::authorize('transfer', $from);
        Gate::authorize('transfer', $to);

        try {
            $amount = Money::parse($data['amount']);
        } catch (InvalidArgumentException) {
            return back()->withInput()->withErrors(['amount' => 'Enter an amount such as 250.00.']);
        }

        try {
            $tx = $ledger->execute(new TransferRequest(
                type: TransactionType::Transfer,
                amount: $amount,
                idempotencyKey: 'member:'.$request->user()->id.':'.$data['token'],
                fromAccountId: $from->id,
                toAccountId: $to->id,
                initiatedBy: $request->user()->id,
                impersonatorId: $acting['admin_id'] ?? null,
                memo: $data['memo'] ?? null,
                memberDescription: null,
            ));
        } catch (TransferException $e) {
            return back()->withInput()->withErrors(['amount' => $e->getMessage()]);
        }

        $message = $tx->status === TransactionStatus::Completed
            ? "Your transfer of {$amount->format()} is complete."
            : "Your transfer of {$amount->format()} is being reviewed. The money is set aside and we will update you shortly.";

        return redirect()->route('transactions.show', $tx->reference)->with('status', $message);
    }
}
