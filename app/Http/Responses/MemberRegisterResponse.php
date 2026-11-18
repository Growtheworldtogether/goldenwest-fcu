<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\RegisterResponse;

/** A new member always lands on the welcome page, then their own dashboard, never on a page a previous visitor tried to open. */
class MemberRegisterResponse implements RegisterResponse
{
    public function toResponse($request)
    {
        $request->session()->forget('url.intended');
        $request->session()->put('welcome_until', now()->addMinutes(30)->timestamp);

        return $request->wantsJson() ? new JsonResponse('', 201) : redirect()->route('welcome');
    }
}
