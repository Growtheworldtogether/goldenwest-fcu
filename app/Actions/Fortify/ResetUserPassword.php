<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Services\Audit;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()],
        ])->validate();

        $user->forceFill(['password' => $input['password']])->save();
        Audit::record('password.reset', $user, null, null, $user->id);
        \App\Notifications\MemberNotice::send($user, 'Your password was reset', ['The password for your online banking was just reset.', 'If you made this change, you do not need to do anything. If you did not, call Customer Care right away at '.brand('support_phone').'.'], null, null, true);
    }
}
