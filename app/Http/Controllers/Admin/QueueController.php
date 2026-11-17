<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TransactionStatus;
use App\Http\Controllers\Controller;
use App\Models\ReasonCode;
use App\Models\Transaction;
use App\Models\User;
use App\Support\AccountOwners;
use App\Services\Ledger\TransferException;
use App\Services\Ledger\TransferService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class QueueController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('staff.viewQueue');
        $filter = in_array($request->query('status'), ['pending', 'held', 'all'], true) ? $request->query('status') : 'open';

        $rows = Transaction::with(['fromAccount.product', 'toAccount'])
            ->when($filter === 'open', fn ($q) => $q->whereIn('status', ['pending', 'held']))
            ->when(in_array($filter, ['pending', 'held']), fn ($q) => $q->where('status', $filter))
            ->when($filter === 'all', fn ($q) => $q->whereIn('status', ['pending', 'held', 'rejected', 'completed', 'reversed', 'failed']))
            ->orderBy($filter === 'all' ? 'id' : 'created_at', $filter === 'all' ? 'desc' : 'asc')
            ->paginate(25)->withQueryString();

        return view('admin.queue', ['rows' => $rows, 'filter' => $filter, 'ownerOf' => AccountOwners::for($rows)]);
    }

    public function show(Transaction $transaction)
    {
        Gate::authorize('staff.viewQueue');
        $transaction->load(['fromAccount.product', 'toAccount.product', 'decisions.decider', 'entries']);
        $approvers = $transaction->decisions->where('decision', 'approved')->pluck('decided_by')->unique()->count();

        return view('admin.transaction', [
            'tx' => $transaction,
            'owners' => AccountOwners::for([$transaction]),
            'approvers' => $approvers,
            'needed' => $transaction->amount->cents >= config('bank.dual_approval_threshold') ? 2 : 1,
            'canDecide' => Gate::allows('staff.decideTransaction', $transaction),
            'canReverse' => Gate::allows('staff.adjustBalance', $transaction->amount->cents),
            'reasons' => ReasonCode::whereIn('applies_to', ['decision', 'all'])->orderBy('label')->pluck('label', 'code'),
            'allReasons' => ReasonCode::pluck('label', 'code'),
            'deposit' => \App\Models\Deposit::with('payee')->where('transaction_id', $transaction->id)->first(),
            'wire' => \App\Models\WireTransfer::where('transaction_id', $transaction->id)->first(),
            'tickets' => \App\Models\Ticket::with('memberMessages')->where('transaction_id', $transaction->id)->latest('id')->get(),
            'initiator' => $transaction->initiated_by ? User::find($transaction->initiated_by) : null,
            'impersonator' => $transaction->impersonator_id ? User::find($transaction->impersonator_id) : null,
        ]);
    }

    public function act(Request $request, Transaction $transaction, string $action, TransferService $ledger)
    {
        $user = $request->user();
        Gate::authorize('staff.decideTransaction', $transaction);

        try {
            $result = match ($action) {
                'approve' => $ledger->approve($transaction, $user, $request->validate(['note' => ['nullable', 'string', 'max:500']])['note'] ?? null),
                'release' => $ledger->release($transaction, $user, $request->validate(['note' => ['nullable', 'string', 'max:500']])['note'] ?? null),
                'reject' => $this->reject($request, $ledger, $transaction),
                'hold' => $this->hold($request, $ledger, $transaction),
                'reverse' => $this->reverse($request, $ledger, $transaction),
            };
        } catch (TransferException $e) {
            return back()->withErrors(['action' => $e->getMessage()]);
        }

        $message = match (true) {
            $action === 'approve' && $result->status === TransactionStatus::Pending => 'Your approval is recorded. A second, different approver is needed before this posts.',
            $action === 'approve' => 'Approved and posted.',
            $action === 'reject' => 'Rejected. The reserved money was released and the member sees the reason.',
            $action === 'hold' => 'Placed on hold. The member sees the reason.',
            $action === 'release' => 'Hold released. It is back in the queue.',
            default => 'Reversed with a new transaction.',
        };

        return redirect()->route('admin.queue.show', $transaction->reference)->with('status', $message);
    }

    private function reject(Request $r, TransferService $ledger, Transaction $tx)
    {
        $d = $r->validate(['reason_code' => ['required', 'exists:reason_codes,code'], 'reason_text' => ['required', 'string', 'max:1000']]);

        return $ledger->reject($tx, $r->user(), $d['reason_code'], $d['reason_text']);
    }

    private function hold(Request $r, TransferService $ledger, Transaction $tx)
    {
        $d = $r->validate(['reason_code' => ['required', 'exists:reason_codes,code'], 'reason_text' => ['required', 'string', 'max:1000'], 'until' => ['nullable', 'date', 'after:today']]);

        return $ledger->hold($tx, $r->user(), $d['reason_code'], $d['reason_text'], ! empty($d['until']) ? Carbon::parse($d['until']) : null);
    }

    private function reverse(Request $r, TransferService $ledger, Transaction $tx)
    {
        Gate::authorize('staff.adjustBalance', $tx->amount->cents);
        $d = $r->validate(['reason_code' => ['required', 'exists:reason_codes,code'], 'reason_text' => ['required', 'string', 'max:1000']]);

        return $ledger->reverse($tx, $r->user(), $d['reason_code'], $d['reason_text']);
    }
}
