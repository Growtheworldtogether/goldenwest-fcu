<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\MemberNotice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Remembers the browsers a member has signed in from and emails them when a new one appears. */
class DeviceWatch
{
    public static function seen(User $user, Request $request): bool
    {
        $agent = (string) $request->userAgent();
        $fingerprint = hash('sha256', $user->id.'|'.preg_replace('/[\d.]+/', '', $agent));
        $now = now();
        $known = DB::table('login_devices')->where('user_id', $user->id)->exists();
        $row = DB::table('login_devices')->where('user_id', $user->id)->where('fingerprint', $fingerprint)->first();
        if ($row) {
            DB::table('login_devices')->where('id', $row->id)->update(['last_seen_at' => $now]);

            return false;
        }
        DB::table('login_devices')->insert(['user_id' => $user->id, 'fingerprint' => $fingerprint, 'label' => substr($agent, 0, 120), 'first_seen_at' => $now, 'last_seen_at' => $now]);
        if ($known) { // the first sign-in ever is not news
            MemberNotice::send($user, 'New sign-in to your '.brand('short_name').' account', [
                'We noticed a sign-in to your online banking from a device we have not seen before, on '.$now->format('F j, Y').'.',
                'If this was you, you do not need to do anything. If it was not, change your password now and call Customer Care at '.brand('support_phone').'.',
            ], 'Review your security settings', route('security'), true);

            return true;
        }

        return false;
    }
}
