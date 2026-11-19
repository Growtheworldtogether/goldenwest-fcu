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
            'securityQuestions' => \App\Services\SecurityQuestions::enabled(),
            'managersEditSite' => Setting::bool('managers_edit_site', false),
            'moneyActions' => Setting::bool('impersonation_allow_money_actions', config('admin.impersonation_allow_money_actions')),
            'wireFee' => \App\Services\WireService::fee()->cents / 100,
            'wireFeeIntl' => \App\Services\WireService::fee(true)->cents / 100,
            'wireCutoff' => \App\Services\WireService::cutoff(),
            'regionCodes' => \App\Support\Region::codes(),
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

        $oldSq = \App\Services\SecurityQuestions::enabled();
        $newSq = $request->boolean('security_questions');
        if ($oldSq !== $newSq) {
            Setting::put('security_questions', $newSq);
            Audit::record('setting.changed', null, ['security_questions' => $oldSq], ['security_questions' => $newSq], $request->user()->id);
        }

        $oldSite = Setting::bool('managers_edit_site', false);
        $newSite = $request->boolean('managers_edit_site');
        if ($oldSite !== $newSite) {
            Setting::put('managers_edit_site', $newSite);
            \App\Services\StaffDirectory::sync();
            Audit::record('setting.changed', null, ['managers_edit_site' => $oldSite], ['managers_edit_site' => $newSite], $request->user()->id);
        }

        $wire = $request->validate(['wire_fee' => ['nullable', 'numeric', 'min:0', 'max:500'], 'wire_fee_intl' => ['nullable', 'numeric', 'min:0', 'max:500'], 'wire_cutoff' => ['nullable', 'date_format:H:i']]);
        if (isset($wire['wire_fee']) || isset($wire['wire_fee_intl']) || isset($wire['wire_cutoff'])) {
            $oldWire = ['fee' => \App\Services\WireService::fee()->cents, 'fee_intl' => \App\Services\WireService::fee(true)->cents, 'cutoff' => \App\Services\WireService::cutoff()];
            if (isset($wire['wire_fee_intl'])) {
                Setting::set('wire_fee_intl_cents', (int) round($wire['wire_fee_intl'] * 100));
            }
            if (isset($wire['wire_fee'])) {
                Setting::set('wire_fee_cents', (int) round($wire['wire_fee'] * 100));
            }
            if (isset($wire['wire_cutoff'])) {
                Setting::set('wire_cutoff', $wire['wire_cutoff']);
            }
            $newWire = ['fee' => \App\Services\WireService::fee()->cents, 'fee_intl' => \App\Services\WireService::fee(true)->cents, 'cutoff' => \App\Services\WireService::cutoff()];
            if ($oldWire !== $newWire) {
                Audit::record('setting.changed', null, ['wires' => $oldWire], ['wires' => $newWire], $request->user()->id);
            }
        }

        if ($request->has('region_form')) {
            $regionInput = $request->validate(['region_states' => ['required', 'array', 'min:1'], 'region_states.*' => ['string', \Illuminate\Validation\Rule::in(array_keys(\App\Support\Region::STATES))]], ['region_states.required' => 'Choose at least one state where membership is open.']);
            $oldRegion = \App\Support\Region::codes();
            \App\Support\Region::save($regionInput['region_states']);
            $newRegion = \App\Support\Region::codes();
            if ($oldRegion !== $newRegion) {
                Audit::record('setting.changed', null, ['region_states' => $oldRegion], ['region_states' => $newRegion], $request->user()->id);
            }
        }

        return back()->with('status', 'Settings saved.');
    }
}
