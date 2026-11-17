<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SettingsController extends Controller
{
    public function edit()
    {
        Gate::authorize('staff.manageAdmin');

        return view('admin.settings', [
            'managersEditSite' => Setting::bool('managers_edit_site', false),
            'moneyActions' => Setting::bool('impersonation_allow_money_actions', config('admin.impersonation_allow_money_actions')),
            'staff' => User::where('role', 'admin')->with('adminProfile')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request)
    {
        Gate::authorize('staff.manageAdmin');
        $new = $request->boolean('impersonation_allow_money_actions');
        $old = Setting::bool('impersonation_allow_money_actions', config('admin.impersonation_allow_money_actions'));
        Setting::put('impersonation_allow_money_actions', $new);
        Audit::record('setting.changed', null, ['impersonation_allow_money_actions' => $old], ['impersonation_allow_money_actions' => $new], $request->user()->id);

        $oldSite = Setting::bool('managers_edit_site', false);
        $newSite = $request->boolean('managers_edit_site');
        if ($oldSite !== $newSite) {
            Setting::put('managers_edit_site', $newSite);
            \App\Services\StaffDirectory::sync();
            Audit::record('setting.changed', null, ['managers_edit_site' => $oldSite], ['managers_edit_site' => $newSite], $request->user()->id);
        }

        return back()->with('status', 'Settings saved.');
    }
}
