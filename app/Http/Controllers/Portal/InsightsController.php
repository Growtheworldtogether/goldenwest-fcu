<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\SpendingService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class InsightsController extends Controller
{
    public function index(Request $request, SpendingService $spending)
    {
        $month = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('month')) ? Carbon::createFromFormat('Y-m-d', $request->query('month').'-01')->startOfMonth() : today()->startOfMonth();
        if ($month->gt(today())) {
            $month = today()->startOfMonth();
        }
        $data = $spending->month($request->user(), $month);

        return view('portal.insights', [
            'month' => $month, 'prev' => $month->copy()->subMonth(), 'next' => $month->copy()->addMonth()->gt(today()) ? null : $month->copy()->addMonth(),
            'rows' => $data['spend'], 'income' => $data['income'], 'spent' => array_sum(array_map(fn ($r) => $r['tx']->amount->cents + $r['tx']->fee->cents, $data['spend'])),
            'byCategory' => $spending->byCategory($data['spend']), 'merchants' => $spending->topMerchants($data['spend']),
            'budgets' => $spending->budgetStatus($request->user(), $month), 'categories' => config('spending.categories'),
        ]);
    }

    public function budget(Request $request, SpendingService $spending)
    {
        $d = $request->validate(['category' => ['required', 'string'], 'limit' => ['nullable', 'numeric', 'min:0']]);
        $spending->setBudget($request->user(), $d['category'], $request->filled('limit') ? (int) round($d['limit'] * 100) : null);

        return back()->with('toast', ['type' => 'ok', 'message' => $request->filled('limit') && $d['limit'] > 0 ? 'Budget saved.' : 'Budget removed.']);
    }

    public function category(Request $request, Transaction $transaction, SpendingService $spending)
    {
        $spending->setCategory($request->user(), $transaction, $request->validate(['category' => ['required', 'string']])['category']);

        return back()->with('toast', ['type' => 'ok', 'message' => 'Category updated.']);
    }
}
