<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Services\Audit;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

class UpdateUserPassword implements UpdatesUserPasswords
{
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()],
        ], ['current_password.current_password' => 'The current password is incorrect.'])->validateWithBag('updatePassword');

        $user->forceFill(['password' => $input['password']])->save();
        Audit::record('password.changed', $user, null, null, $user->id);
        \App\Notifications\MemberNotice::send($user, 'Your password was changed', ['The password for your online banking was just changed.', 'If you made this change, you do not need to do anything. If you did not, call Customer Care right away at '.brand('support_phone').'.'], null, null, true);
    }
}
