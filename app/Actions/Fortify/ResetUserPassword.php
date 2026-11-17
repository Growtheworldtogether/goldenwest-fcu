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
    }
}
