<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ImpersonationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ImpersonationController extends Controller
{
    public function start(Request $request, User $user, ImpersonationService $service)
    {
        Gate::authorize('staff.impersonate', $user);
        $data = $request->validate([
            'reason_code' => ['required', 'exists:reason_codes,code'],
            'reason_text' => ['nullable', 'string', 'max:500'],
            'ticket' => ['nullable', 'string', 'max:60'],
        ]);

        $service->start($request, $request->user(), $user, $data['reason_code'], $data['reason_text'] ?? null, $data['ticket'] ?? null);

        return redirect()->route('dashboard');
    }

    public function stop(Request $request, ImpersonationService $service)
    {
        abort_unless($request->session()->has('impersonation'), 404);
        $service->end($request);

        return redirect()->route('admin.home')->with('status', 'You are back in the staff console.');
    }
}
