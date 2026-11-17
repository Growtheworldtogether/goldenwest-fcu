<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\JointRequest;
use App\Models\ReasonCode;
use App\Models\User;
use App\Services\DeceasedService;
use App\Services\JointService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class JointController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('staff.approveJoint');
        $tab = $request->query('tab') === 'consent' ? 'consent' : ($request->query('tab') === 'all' ? 'all' : 'review');

        return view('admin.joint', [
            'tab' => $tab,
            'counts' => ['review' => JointRequest::where('type', 'invite')->where('status', 'review')->count(), 'consent' => JointRequest::where('type', 'removal')->where('status', 'consent')->count()],
            'requests' => JointRequest::with(['account.product', 'requester', 'subject'])
                ->when($tab === 'review', fn ($q) => $q->where('type', 'invite')->where('status', 'review')->orderBy('id'))
                ->when($tab === 'consent', fn ($q) => $q->where('type', 'removal')->where('status', 'consent')->orderBy('id'))
                ->when($tab === 'all', fn ($q) => $q->latest('id'))
                ->paginate(25)->withQueryString(),
            'reasons' => ReasonCode::whereIn('applies_to', ['decision', 'all'])->orderBy('label')->pluck('label', 'code'),
        ]);
    }

    public function decide(Request $request, JointRequest $req, JointService $svc)
    {
        Gate::authorize('staff.approveJoint');
        $d = $request->validate(['decision' => ['required', 'in:approve,reject'], 'reason_code' => ['nullable', 'exists:reason_codes,code'], 'reason_text' => ['nullable', 'string', 'max:500']]);
        try {
            $svc->decide($req, $request->user(), $d['decision'] === 'approve', $d['reason_code'] ?? null, $d['reason_text'] ?? null);
        } catch (InvalidArgumentException $e) {
            return back()->with('toast', ['type' => 'bad', 'message' => $e->getMessage()]);
        }

        return back()->with('toast', ['type' => 'ok', 'message' => $d['decision'] === 'approve' ? 'Approved. Both holders can see the account now.' : 'Declined. Both members can see the reason.']);
    }

    public function remove(Request $request, User $user, JointService $svc)
    {
        Gate::authorize('staff.approveJoint');
        $d = $request->validate(['account_id' => ['required', 'integer'], 'reason_code' => ['required', 'exists:reason_codes,code'], 'reason_text' => ['required', 'string', 'max:500']]);
        try {
            $svc->adminRemove(Account::findOrFail($d['account_id']), $user, $request->user(), $d['reason_code'], $d['reason_text']);
        } catch (InvalidArgumentException $e) {
            return back()->with('toast', ['type' => 'bad', 'message' => $e->getMessage()]);
        }

        return back()->with('toast', ['type' => 'ok', 'message' => 'Holder removed from the account.']);
    }

    public function deceased(Request $request, User $user, DeceasedService $svc)
    {
        Gate::authorize('staff.lockAccounts');
        abort_if($user->isAdmin(), 404);
        $note = $request->validate(['note' => ['required', 'string', 'max:1000']])['note'];
        try {
            $svc->mark($user, $request->user(), $note);
        } catch (InvalidArgumentException $e) {
            return back()->with('toast', ['type' => 'bad', 'message' => $e->getMessage()]);
        }

        return back()->with('toast', ['type' => 'ok', 'message' => 'Marked as deceased. Sole accounts are locked and joint accounts stay open for the other holder.']);
    }
}
