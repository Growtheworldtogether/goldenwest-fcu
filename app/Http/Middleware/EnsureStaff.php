<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Staff area gate: an admin with a title, two-step verification on, and a short idle timeout. */
class EnsureStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user && $user->isAdmin() && $user->adminProfile, 403);

        $last = $request->session()->get('staff_last_activity');
        if ($last && time() - $last > config('admin.idle_timeout_minutes') * 60) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', 'You were signed out after a period of inactivity. Please sign in again.');
        }
        $request->session()->put('staff_last_activity', time());

        if (! $user->hasTwoFactor() && ! $request->routeIs('admin.security')) {
            return redirect()->route('admin.security')->with('error', 'Turn on two-step verification to use the staff console.');
        }

        return $next($request);
    }
}
