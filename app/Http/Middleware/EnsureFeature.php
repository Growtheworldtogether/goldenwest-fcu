<?php

namespace App\Http\Middleware;

use App\Services\Features;
use Closure;
use Illuminate\Http\Request;

class EnsureFeature
{
    public function handle(Request $request, Closure $next, string $feature)
    {
        abort_unless($request->user() && Features::enabled($request->user(), $feature), 404);

        return $next($request);
    }
}
