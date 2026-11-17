<?php

namespace App\Http\Middleware;

use App\Services\ImpersonationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Expires "log in as member" sessions and blocks credential changes while one is running. */
class ApplyImpersonation
{
    /** Password, email, contact and two-step changes stay with the member; staff use the reset tool. */
    public const BLOCKED = [
        'user-password.update', 'user-profile-information.update', 'password.update', 'password.confirm',
        'two-factor.enable', 'two-factor.confirm', 'two-factor.disable', 'two-factor.regenerate-recovery-codes',
        'two-factor.recovery-codes', 'two-factor.secret-key', 'two-factor.qr-code',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $imp = $request->hasSession() ? $request->session()->get('impersonation') : null;

        if ($imp) {
            if (now()->timestamp > $imp['expires_at']) {
                app(ImpersonationService::class)->end($request, 'expired');

                return redirect()->route('admin.home')->with('status', 'The member session expired after 30 minutes.');
            }
            if ($request->routeIs(...self::BLOCKED)) {
                abort(403, 'This is not available while acting as a member.');
            }
        }

        return $next($request);
    }
}
