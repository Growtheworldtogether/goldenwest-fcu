<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\ReasonCode;
use App\Models\Transaction;
use App\Services\ActivityQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TransactionController extends Controller
{
    public function show(Request $request, Transaction $transaction)
    {
        Gate::authorize('view', $transaction);

        $transaction->load(['fromAccount', 'toAccount', 'decisions' => fn ($q) => $q->latest('id')]);
        $activity = new ActivityQuery($request->user());

        return view('portal.transaction', [
            'tx' => $transaction,
            'rows' => $activity->rows([$transaction]),
            'decisions' => $transaction->decisions,
            'reasons' => ReasonCode::pluck('label', 'code'),
            'accounts' => $activity->accounts,
        ]);
    }

    /** "Contest this decision" opens a ticket linked to the transaction, or returns the one already open. */
    public function contest(Request $request, Transaction $transaction, \App\Services\TicketService $tickets)
    {
        Gate::authorize('view', $transaction);
        $data = $request->validate(['message' => ['nullable', 'string', 'max:1000']]);

        try {
            [$ticket, $created] = $tickets->contest($request->user(), $transaction, $data['message'] ?? null);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('tickets.show', $ticket->reference)->with('toast', $created
            ? ['type' => 'ok', 'message' => 'We opened ticket '.$ticket->reference.'. A person will review this decision again.']
            : ['type' => 'info', 'message' => 'You already have an open ticket for this. Ticket '.$ticket->reference.'.']);
    }
}
