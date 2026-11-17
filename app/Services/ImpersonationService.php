<?php

namespace App\Services;

use App\Models\Impersonation;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

/** "Log in as member": the session switches to the member and a banner stays on until it ends. */
final class ImpersonationService
{
    public function start(Request $request, User $admin, User $member, string $reasonCode, ?string $reasonText, ?string $ticket): Impersonation
    {
        if ($request->session()->has('impersonation')) {
            throw ValidationException::withMessages(['reason_code' => 'Exit the current member session first.']);
        }
        if ($member->isAdmin() || $member->is($admin)) {
            throw ValidationException::withMessages(['reason_code' => 'You can only act as a member.']);
        }
        if ($member->locked_until?->isFuture() || ! $member->hasVerifiedEmail()) {
            throw ValidationException::withMessages(['reason_code' => 'This member is not active.']);
        }

        $imp = Impersonation::create([
            'admin_id' => $admin->id, 'member_id' => $member->id, 'reason_code' => $reasonCode,
            'reason_text' => $reasonText, 'ticket_reference' => $ticket, 'mode' => 'full', 'ip' => $request->ip(),
            'started_at' => now(), 'expires_at' => now()->addMinutes(config('admin.impersonation_minutes')),
        ]);
        Audit::record('impersonation.started', $member, null, ['reason' => $reasonCode, 'ticket' => $ticket], $admin->id);

        $request->attributes->set('silent_login', true);
        Auth::login($member);
        $request->session()->regenerate();
        $request->session()->put('impersonation', [
            'id' => $imp->id, 'admin_id' => $admin->id, 'member_id' => $member->id,
            'admin_name' => $admin->name, 'admin_two_factor' => $admin->hasTwoFactor(), 'member_name' => $member->name, 'expires_at' => $imp->expires_at->timestamp,
        ]);

        return $imp;
    }

    public function end(Request $request, string $how = 'exited'): void
    {
        $data = $request->session()->get('impersonation');
        if (! $data) {
            return;
        }

        $imp = Impersonation::find($data['id']);
        $imp?->update(['ended_at' => now(), 'ended_reason' => $how]);
        Audit::record('impersonation.ended', User::find($data['member_id']), null, ['how' => $how], $data['admin_id'], $data['admin_id']);

        $request->session()->forget('impersonation');
        $request->attributes->set('silent_login', true);
        Auth::loginUsingId($data['admin_id']);
        $request->session()->regenerate();
        $request->session()->put('staff_last_activity', time());
    }

    public function moneyActionsAllowed(): bool
    {
        return Setting::bool('impersonation_allow_money_actions', config('admin.impersonation_allow_money_actions'));
    }

    /**
     * The staff member's own proof for every money action while acting as a member: their two-step
     * code when they have two-step on, otherwise their account password.
     */
    public function adminCredentialIsValid(int $adminId, ?string $input): bool
    {
        $admin = User::find($adminId);
        if (! $admin || ! $input) {
            return false;
        }

        if ($admin->hasTwoFactor()) {
            return app(TwoFactorAuthenticationProvider::class)->verify(decrypt($admin->two_factor_secret), $input);
        }

        return Hash::check($input, $admin->password);
    }
}
