<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\Audit;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public const DEFAULTS = ['email_transactions' => true, 'sms_alerts' => false, 'email_marketing' => false];

    public function edit(Request $request)
    {
        $user = $request->user()->load('profile');

        return view('portal.profile', [
            'user' => $user,
            'prefs' => array_merge(self::DEFAULTS, $user->notification_preferences ?? []),
        ]);
    }

    public function preferences(Request $request)
    {
        $prefs = [];
        foreach (array_keys(self::DEFAULTS) as $key) {
            $prefs[$key] = $request->boolean($key);
        }

        $user = $request->user();
        $before = $user->notification_preferences;
        $user->forceFill(['notification_preferences' => $prefs])->save();
        Audit::record('preferences.updated', $user, $before, $prefs, $user->id);

        return back()->with('status', 'Your notification preferences are saved.');
    }
}
