<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;

/** After sign-in staff land in the staff console and members on their dashboard. */
class StaffAwareLoginResponse implements LoginResponse, TwoFactorLoginResponse
{
    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 204);
        }

        // A member must never be sent to a staff page left over from an earlier visit.
        if (! $request->user()->isAdmin() && str_starts_with((string) parse_url((string) $request->session()->get('url.intended'), PHP_URL_PATH), '/admin')) {
            $request->session()->forget('url.intended');
        }

        return redirect()->intended($request->user()->isAdmin() ? '/admin' : '/dashboard');
    }
}
