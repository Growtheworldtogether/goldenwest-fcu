<?php

namespace App\Http\Controllers\Portal;

use App\Enums\TransactionStatus;
use App\Http\Controllers\Controller;
use App\Models\Decision;
use App\Models\ReasonCode;
use App\Models\Transaction;
use App\Services\ActivityQuery;
use App\Support\Money;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $activity = new ActivityQuery($request->user());
        $accounts = $activity->accounts->where('status', 'active');
        $ids = $accounts->keys()->all();

        $recent = $activity->rows($activity->query($request)->limit(12)->get());
        $recent = array_slice($recent, 0, 8);

        // Anything waiting on us, with the latest reason, so a member is never left guessing.
        $waiting = Transaction::with('decisions')
            ->whereIn('from_account_id', $ids)
            ->whereIn('status', [TransactionStatus::Pending->value, TransactionStatus::Held->value])
            ->latest('id')->get();

        return view('portal.dashboard', [
            'accounts' => $accounts,
            'total' => Money::cents($accounts->sum(fn ($a) => $a->balance->cents)),
            'recent' => $recent,
            'waiting' => $waiting,
            'reasons' => ReasonCode::pluck('label', 'code'),
        ]);
    }
}
