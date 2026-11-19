<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Until a fresh sign in has answered its security question (or set its questions), the only pages open are the ones for that. */
class SecurityChallenge
{
    public function handle(Request $request, Closure $next)
    {
        $state = $request->hasSession() ? $request->session()->get('sq_pending') : null;
        if (! $state || ! $request->user()) {
            return $next($request);
        }
        if ($request->routeIs('security.check*', 'security.questions.setup*', 'logout', 'setup', 'setup.save')) {
            return $next($request);
        }

        return redirect()->route($state === 'setup' ? 'security.questions.setup' : 'security.check');
    }
}
