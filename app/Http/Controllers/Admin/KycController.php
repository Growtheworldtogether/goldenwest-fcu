<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KycDocument;
use App\Models\ReasonCode;
use App\Services\KycService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class KycController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('staff.reviewKyc');
        $filter = $request->query('status') === 'all' ? 'all' : 'pending';

        $docs = KycDocument::with('user')
            ->when($filter === 'pending', fn ($q) => $q->where('status', 'pending')->orderBy('created_at'))
            ->when($filter === 'all', fn ($q) => $q->where('status', '!=', 'replaced')->orderByDesc('id'))
            ->paginate(25)->withQueryString();

        return view('admin.kyc-index', ['docs' => $docs, 'filter' => $filter]);
    }

    public function show(KycDocument $document)
    {
        Gate::authorize('staff.reviewKyc');
        $document->load(['user', 'decisions.decider']);

        return view('admin.kyc-show', [
            'doc' => $document,
            'others' => KycDocument::where('user_id', $document->user_id)->where('id', '!=', $document->id)->where('status', '!=', 'replaced')->get(),
            'reasons' => ReasonCode::whereIn('applies_to', ['kyc', 'decision', 'all'])->orderBy('label')->pluck('label', 'code'),
            'allReasons' => ReasonCode::pluck('label', 'code'),
        ]);
    }

    public function decide(Request $request, KycDocument $document, string $action, KycService $kyc)
    {
        Gate::authorize('staff.reviewKyc');

        if ($action === 'approve') {
            $kyc->decide($document, $request->user(), true);
            $message = 'Approved.';
        } else {
            $data = $request->validate(['reason_code' => ['required', 'exists:reason_codes,code'], 'reason_text' => ['required', 'string', 'max:1000']], ['reason_text.required' => 'Tell the member what to fix.']);
            $kyc->decide($document, $request->user(), false, $data['reason_code'], $data['reason_text']);
            $message = 'Rejected. The member sees the reason and can upload a new document.';
        }

        $next = KycDocument::where('status', 'pending')->orderBy('created_at')->first();

        return redirect($next ? route('admin.kyc.show', $next) : route('admin.kyc.index'))->with('status', $message.($next ? ' Next in the queue.' : ' The queue is clear.'));
    }
}
