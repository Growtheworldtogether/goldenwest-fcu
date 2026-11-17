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
}
