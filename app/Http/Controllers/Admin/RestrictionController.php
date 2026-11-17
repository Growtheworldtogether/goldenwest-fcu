<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountRestriction;
use App\Models\User;
use App\Services\RestrictionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class RestrictionController extends Controller
{
    public function store(Request $request, User $user, RestrictionService $service)
    {
        Gate::authorize('staff.lockAccounts');
        $data = $request->validate([
            'target' => ['required'],
            'type' => ['required', Rule::in(array_keys(RestrictionService::TYPES))],
            'reason_code' => ['required', 'exists:reason_codes,code'],
            'reason_text' => ['required', 'string', 'max:1000'],
        ]);

        $account = null;
        if ($data['target'] !== 'member') {
            $account = $user->accounts()->where('accounts.id', (int) $data['target'])->firstOrFail();
        }
        $service->lock($request->user(), $account, $user, $data['type'], $data['reason_code'], $data['reason_text']);

        return back()->with('status', 'Lock applied. The member can still sign in and read the reason.');
    }

    public function unlock(Request $request, AccountRestriction $restriction, RestrictionService $service)
    {
        Gate::authorize('staff.lockAccounts');
        $data = $request->validate(['note' => ['required', 'string', 'max:1000']]);
        $service->unlock($request->user(), $restriction, $data['note']);

        return back()->with('status', 'Lock released.');
    }
}
