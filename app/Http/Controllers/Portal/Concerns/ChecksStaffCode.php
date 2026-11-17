<?php

namespace App\Http\Controllers\Portal\Concerns;

use App\Services\ImpersonationService;
use Illuminate\Http\Request;

/** While staff act as a member, every money action needs the staff member's own password or two-step code. */
trait ChecksStaffCode
{
    /** Returns a redirect back when the code is missing or wrong, or null when the action may go ahead. */
    protected function staffCodeProblem(Request $request): ?\Illuminate\Http\RedirectResponse
    {
        $acting = $request->session()->get('impersonation');
        if (! $acting) {
            return null;
        }
        $staff = app(ImpersonationService::class);
        abort_unless($staff->moneyActionsAllowed(), 403, 'Money actions are switched off while acting as a member.');
        $what = ($acting['admin_two_factor'] ?? false) ? 'two-step code' : 'password';
        $request->validate(['admin_code' => ['required', 'string', 'max:200']], ['admin_code.required' => "Enter your own staff {$what} to move money while acting as a member."]);
        if (! $staff->adminCredentialIsValid($acting['admin_id'], $request->input('admin_code'))) {
            return back()->withInput($request->except('admin_code'))->withErrors(['admin_code' => "That staff {$what} is not correct."]);
        }

        return null;
    }
}
