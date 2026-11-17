<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\ReasonCode;
use App\Services\Ledger\TransferException;
use App\Services\Loans\LoanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class LoanController extends Controller
{
    private const TABS = ['review' => 'To review', 'info' => 'Waiting for member', 'loans' => 'Active loans', 'all' => 'All applications'];

    public function index(Request $request)
    {
        Gate::authorize('staff.approveLoans');
        $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'review';

        return view('admin.loans', [
            'tab' => $tab, 'tabs' => self::TABS,
            'apps' => $tab === 'loans' ? null : LoanApplication::with(['user', 'product'])
                ->when($tab === 'review', fn ($q) => $q->whereIn('status', ['submitted', 'in_review'])->orderBy('created_at'))
                ->when($tab === 'info', fn ($q) => $q->where('status', 'needs_info')->orderBy('created_at'))
                ->when($tab === 'all', fn ($q) => $q->latest('id'))->paginate(25)->withQueryString(),
            'loans' => $tab === 'loans' ? Loan::with(['user', 'product'])->whereIn('status', ['active', 'delinquent'])->orderByRaw("case status when 'delinquent' then 0 else 1 end")->orderBy('next_due_date')->paginate(25)->withQueryString() : null,
        ]);
    }

    public function show(LoanApplication $application, LoanService $loans)
    {
        Gate::authorize('staff.approveLoans');
        $loans->startReview($application, request()->user());

        return view('admin.loan-application', [
            'app' => $application->load(['user', 'product', 'depositAccount', 'loan', 'decisions.decider']),
            'reasons' => ReasonCode::whereIn('applies_to', ['decision', 'all'])->orderBy('label')->pluck('label', 'code'),
        ]);
    }

    public function act(Request $request, LoanApplication $application, LoanService $loans)
    {
        Gate::authorize('staff.approveLoans');
        $d = $request->validate([
            'action' => ['required', 'in:approve,reject,info'], 'apr' => ['nullable', 'numeric'], 'amount' => ['nullable', 'numeric'],
            'reason_code' => ['nullable', 'string'], 'reason_text' => ['nullable', 'string', 'max:500'], 'info_request' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            match ($d['action']) {
                'approve' => $loans->approve($application, $request->user(), (int) round(((float) ($d['apr'] ?? 0)) * 100), isset($d['amount']) && $d['amount'] !== '' ? (int) round(((float) $d['amount']) * 100) : null),
                'reject' => $loans->reject($application, $request->user(), $d['reason_code'] ?? '', $d['reason_text'] ?? ''),
                'info' => $loans->requestInfo($application, $request->user(), $d['info_request'] ?? ''),
            };
        } catch (TransferException|InvalidArgumentException $e) {
            return back()->with('toast', ['type' => 'bad', 'message' => $e->getMessage()]);
        }

        return redirect()->route('admin.loans.show', $application)->with('toast', ['type' => 'ok', 'message' => ['approve' => 'Approved. The money was paid into the member\'s account.', 'reject' => 'Application declined. The member can see the reason.', 'info' => 'Question sent to the member.'][$d['action']]]);
    }

    public function loan(Loan $loan)
    {
        Gate::authorize('staff.approveLoans');

        return view('admin.loan', ['loan' => $loan->load(['user', 'product', 'schedule', 'autopayAccount', 'application'])]);
    }
}
