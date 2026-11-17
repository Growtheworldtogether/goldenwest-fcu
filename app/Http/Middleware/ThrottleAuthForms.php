<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;

/** The sign-up, password-reset and resend-verification forms belong to Fortify, so they are limited here. */
class ThrottleAuthForms
{
    private const PATHS = ['register', 'forgot-password', 'reset-password', 'email/verification-notification'];

    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethod('POST') && in_array($request->path(), self::PATHS, true)) {
            return app(ThrottleRequests::class)->handle($request, $next, 'auth-forms');
        }

        return $next($request);
    }
}
