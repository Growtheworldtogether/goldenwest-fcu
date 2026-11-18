<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Impersonation;
use App\Models\ReasonCode;
use App\Models\AccountRestriction;
use App\Services\Audit;
use App\Services\RestrictionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SecurityController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        $sessions = $request->hasSession() && config('session.driver') === 'database'
            ? DB::table('sessions')->where('user_id', $user->id)->where('last_activity', '>=', now()->subMinutes((int) config('session.lifetime'))->timestamp)->orderByDesc('last_activity')->limit(10)->get()->map(fn ($r) => (object) [
                'id' => $r->id, 'ip' => $r->ip_address, 'device' => self::device((string) $r->user_agent),
                'last' => \Illuminate\Support\Carbon::createFromTimestamp($r->last_activity), 'current' => $r->id === $request->session()->getId(),
            ]) : collect();

        return view('portal.security', [
            'sessions' => $sessions,
            'selfLocked' => $user->accounts()->get()->contains(fn ($a) => AccountRestriction::active()->where('account_id', $a->id)->where('reason_code', 'member_request')->exists()),
            'user' => $user,
            'enabled' => $user->two_factor_secret !== null,
            'confirmed' => $user->two_factor_confirmed_at !== null,
            'recoveryCodes' => $user->two_factor_confirmed_at ? $user->recoveryCodes() : [],
            'staffAccess' => Impersonation::with('admin')->where('member_id', $user->id)
                ->whereDoesntHave('admin.roles', fn ($q) => $q->where('name', 'Super Admin'))->latest('id')->limit(10)->get(),
            'staffReasons' => ReasonCode::pluck('label', 'code'),
            'events' => AuditLog::where('actor_id', $user->id)
                ->whereIn('action', ['login.success', 'login.failed', 'password.changed', 'password.reset', 'profile.updated', 'two_factor.enabled', 'two_factor.disabled'])
                ->latest('id')->limit(10)->get(),
        ]);
    }

    /** Sign out every other browser and device. Needs the password again. */
    public function signOutOthers(Request $request)
    {
        $request->validate(['password' => ['required', 'string']], ['password.required' => 'Enter your password to continue.']);
        if (! Hash::check($request->input('password'), $request->user()->password)) {
            return back()->withErrors(['password' => 'That password is not right.'], 'devices');
        }
        $n = DB::table('sessions')->where('user_id', $request->user()->id)->where('id', '!=', $request->session()->getId())->delete();
        Audit::record('sessions.signed_out_others', $request->user(), null, ['count' => $n], $request->user()->id);

        return back()->with('toast', ['type' => 'ok', 'message' => $n ? 'Signed out of '.$n.' other '.($n === 1 ? 'device' : 'devices').'.' : 'There were no other devices signed in.']);
    }

    /** Emergency lock: no money moves in or out until staff unlock after checking it is really the member. */
    public function lockAccounts(Request $request, RestrictionService $locks)
    {
        $request->validate(['password' => ['required', 'string']], ['password.required' => 'Enter your password to lock your accounts.']);
        if (! Hash::check($request->input('password'), $request->user()->password)) {
            return back()->withErrors(['password' => 'That password is not right.'], 'lock');
        }
        $user = $request->user();
        foreach ($user->accounts()->where('accounts.status', 'active')->get() as $account) {
            $already = AccountRestriction::active()->where('account_id', $account->id)->where('type', AccountRestriction::FULL_LOCK)->exists();
            if (! $already) {
                $locks->lock($user, $account, null, AccountRestriction::FULL_LOCK, 'member_request', 'Locked by the member from the security center.');
            }
        }

        return back()->with('toast', ['type' => 'ok', 'message' => 'Your accounts are locked. Call Customer Care at '.brand('support_phone').' to unlock them.']);
    }

    private static function device(string $ua): string
    {
        $os = match (true) {
            str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') => 'iPhone or iPad', str_contains($ua, 'Android') => 'Android', str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Mac OS') => 'Mac', str_contains($ua, 'Linux') => 'Linux', default => 'Unknown device',
        };
        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge', str_contains($ua, 'Firefox') => 'Firefox', str_contains($ua, 'Chrome') => 'Chrome', str_contains($ua, 'Safari') => 'Safari', default => 'browser',
        };

        return $browser.' on '.$os;
    }
}
