<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Staff use the staff console. They only see member pages while acting as a member. */
class EnsureMemberArea
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isAdmin() && ! $request->session()->has('impersonation')) {
            return redirect()->route('admin.home');
        }

        return $next($request);
    }
}
