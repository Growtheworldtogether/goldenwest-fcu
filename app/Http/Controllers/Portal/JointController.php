<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\JointRequest;
use App\Models\User;
use App\Services\JointService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class JointController extends Controller
{
    private function run(callable $fn, string $ok)
    {
        try {
            $fn();
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('toast', ['type' => 'bad', 'message' => $e->getMessage()]);
        }

        return back()->with('toast', ['type' => 'ok', 'message' => $ok]);
    }

    public function index(Request $request, JointService $svc)
    {
        $u = $request->user();
        $accounts = $u->accounts()->with('product')->where('accounts.type', 'standard')->orderBy('accounts.id')->get();
        $ids = $accounts->pluck('id');

        return view('portal.joint', [
            'accounts' => $accounts,
            'holders' => $accounts->mapWithKeys(fn ($a) => [$a->id => $svc->activeHolders($a)]),
            'incomingInvites' => JointRequest::with(['account.product', 'requester'])->where('type', 'invite')->where('status', 'invited')->where('subject_user_id', $u->id)->latest('id')->get(),
            'consents' => JointRequest::with(['account.product', 'requester'])->where('type', 'removal')->where('status', 'consent')->where('subject_user_id', $u->id)->latest('id')->get(),
            'open' => JointRequest::with(['account', 'subject'])->whereIn('account_id', $ids)->whereIn('status', ['invited', 'review', 'consent'])->where('subject_user_id', '!=', $u->id)->latest('id')->get(),
            'recent' => JointRequest::with(['account', 'subject'])->whereIn('account_id', $ids)->whereIn('status', ['rejected', 'declined'])->where('updated_at', '>', now()->subDays(30))->latest('id')->get(),
            'verified' => $u->kyc_status === 'verified',
            'pick' => (int) $request->query('account'),
        ]);
    }

    public function invite(Request $request, JointService $svc)
    {
        $d = $request->validate(['account_id' => ['required', 'integer'], 'email' => ['required', 'email', 'max:190'], 'message' => ['nullable', 'string', 'max:200']]);
        $account = $request->user()->accounts()->where('accounts.id', $d['account_id'])->firstOrFail();
        Gate::authorize('view', $account);

        return $this->run(fn () => $svc->invite($request->user(), $account, $d['email'], $d['message'] ?? null), 'Invitation sent. They will see it when they sign in.');
    }

    public function respond(Request $request, JointRequest $req, JointService $svc)
    {
        $action = $request->validate(['action' => ['required', 'in:accept,decline']])['action'];
        abort_unless($req->subject_user_id === $request->user()->id, 404);

        return $this->run(fn () => $svc->respondToInvite($req, $request->user(), $action === 'accept'),
            $action === 'accept' ? 'Thanks. A team member will review this and let you know.' : 'Invitation declined.');
    }

    public function cancel(Request $request, JointRequest $req, JointService $svc)
    {
        abort_unless($request->user()->accounts()->where('accounts.id', $req->account_id)->exists(), 404);

        return $this->run(fn () => $req->type === 'invite' ? $svc->cancelInvite($req, $request->user()) : $svc->cancelRemoval($req, $request->user()), 'Request cancelled.');
    }

    public function requestRemoval(Request $request, JointService $svc)
    {
        $d = $request->validate(['account_id' => ['required', 'integer'], 'user_id' => ['required', 'integer']]);
        $account = $request->user()->accounts()->where('accounts.id', $d['account_id'])->firstOrFail();
        $target = User::findOrFail($d['user_id']);

        return $this->run(fn () => $svc->requestRemoval($request->user(), $account, $target), 'We asked them to agree. If they do not, Customer Care can help.');
    }

    public function consent(Request $request, JointRequest $req, JointService $svc)
    {
        $action = $request->validate(['action' => ['required', 'in:agree,decline']])['action'];
        abort_unless($req->subject_user_id === $request->user()->id, 404);

        return $this->run(fn () => $svc->respondToRemoval($req, $request->user(), $action === 'agree'),
            $action === 'agree' ? 'You are no longer a holder of that account.' : 'You stay on the account.');
    }

    public function leave(Request $request, JointService $svc)
    {
        $account = Account::findOrFail($request->validate(['account_id' => ['required', 'integer']])['account_id']);
        abort_unless($request->user()->accounts()->where('accounts.id', $account->id)->exists(), 404);

        return $this->run(fn () => $svc->leave($request->user(), $account), 'You are no longer a holder of '.$account->nickname.'.');
    }
}
