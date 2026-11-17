<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class SecurityController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        return view('portal.security', [
            'user' => $user,
            'enabled' => $user->two_factor_secret !== null,
            'confirmed' => $user->two_factor_confirmed_at !== null,
            'recoveryCodes' => $user->two_factor_confirmed_at ? $user->recoveryCodes() : [],
            'events' => AuditLog::where('actor_id', $user->id)
                ->whereIn('action', ['login.success', 'login.failed', 'password.changed', 'password.reset', 'profile.updated', 'two_factor.enabled', 'two_factor.disabled'])
                ->latest('id')->limit(10)->get(),
        ]);
    }
}
