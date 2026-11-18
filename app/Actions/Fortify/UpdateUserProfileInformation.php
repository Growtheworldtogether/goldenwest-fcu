<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Services\Audit;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

/** Members may change contact details. Name and email changes go through Customer Care so identity stays verified. */
class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            'phone' => ['required', 'string', 'max:30'],
            'address_line1' => ['required', 'string', 'max:160'],
            'city' => ['required', 'string', 'max:80'],
            'state' => ['required', 'string', 'size:2'],
            'postal_code' => ['required', 'string', 'regex:/^\d{5}(-\d{4})?$/'],
        ])->validateWithBag('updateProfileInformation');

        $before = $user->only(['phone', 'address_line1', 'city', 'state', 'postal_code']);
        $user->forceFill([
            'phone' => $input['phone'],
            'address_line1' => $input['address_line1'],
            'city' => $input['city'],
            'state' => strtoupper($input['state']),
            'postal_code' => $input['postal_code'],
        ])->save();

        Audit::record('profile.updated', $user, $before, $user->only(array_keys($before)), $user->id);
        \App\Notifications\MemberNotice::send($user, 'Your contact details were updated', ['The phone number or address on your account was just changed.', 'If you made this change, you do not need to do anything. If you did not, call Customer Care right away at '.brand('support_phone').'.'], null, null, true);
    }
}
