<?php

namespace App\Actions\Fortify;

use App\Models\MemberProfile;
use App\Models\User;
use App\Services\AccountOpening;
use App\Services\Audit;
use App\Support\AccountNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    /** Ways to qualify for membership. Everything except family_member needs a connection to the five-state region. */
    public const FIELDS = [
        'lives_in_region' => 'I live in one of these states',
        'works_in_region' => 'I work in one of these states',
        'attends_school' => 'I attend school in one of these states',
        'worships_in_region' => 'I worship in one of these states',
        'family_member' => 'A family member is a Golden West member',
    ];

    public function create(array $input): User
    {
        $states = config('bank.region_states');

        Validator::make($input, [
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'string', 'email', 'max:160', Rule::unique(User::class)],
            'phone' => ['required', 'string', 'max:30'],
            'dob' => ['required', 'date', 'before_or_equal:'.now()->subYears(18)->toDateString()],
            'address_line1' => ['required', 'string', 'max:160'],
            'city' => ['required', 'string', 'max:80'],
            'state' => ['required', 'string', 'size:2'],
            'postal_code' => ['required', 'string', 'regex:/^\d{5}(-\d{4})?$/'],
            'ssn_last4' => ['required', 'digits:4'],
            'field_of_membership' => ['required', Rule::in(array_keys(self::FIELDS))],
            'eligibility_state' => ['required_unless:field_of_membership,family_member', 'nullable', Rule::in($states)],
            'referred_by_member_number' => ['required_if:field_of_membership,family_member', 'nullable', 'digits:7', Rule::exists('users', 'member_number')],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()],
            'terms' => ['accepted'],
        ], [
            'dob.before_or_equal' => 'You must be at least 18 to open an account.',
            'eligibility_state.required_unless' => 'Choose the state that connects you to Golden West.',
            'eligibility_state.in' => 'We serve California, Nevada, Arizona, Oregon and Washington.',
            'referred_by_member_number.exists' => 'We could not find that member number.',
            'referred_by_member_number.required_if' => 'Enter your family member\'s member number.',
            'terms.accepted' => 'Please agree to the Terms of Use and Privacy notice.',
        ])->validate();

        return DB::transaction(function () use ($input) {
            $user = User::create([
                'name' => $input['first_name'].' '.$input['last_name'],
                'first_name' => $input['first_name'],
                'last_name' => $input['last_name'],
                'email' => $input['email'],
                'phone' => $input['phone'],
                'dob' => $input['dob'],
                'address_line1' => $input['address_line1'],
                'city' => $input['city'],
                'state' => strtoupper($input['state']),
                'postal_code' => $input['postal_code'],
                'password' => $input['password'],
            ]);
            $user->forceFill(['member_number' => AccountNumber::member()])->save();

            MemberProfile::create([
                'user_id' => $user->id,
                'field_of_membership' => $input['field_of_membership'],
                'eligibility_state' => $input['field_of_membership'] === 'family_member' ? null : $input['eligibility_state'],
                'referred_by_member_number' => $input['field_of_membership'] === 'family_member' ? $input['referred_by_member_number'] : null,
                'membership_opened_at' => now()->toDateString(),
                'ssn_last4' => $input['ssn_last4'],
            ]);

            app(AccountOpening::class)->openStarterAccounts($user);
            Audit::record('member.registered', $user, null, ['member_number' => $user->member_number], $user->id);

            return $user;
        });
    }
}
