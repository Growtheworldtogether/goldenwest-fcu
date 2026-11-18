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
    /** Ways to qualify for membership. Everything except family_member needs a connection to the region. */
    public const FIELDS = [
        'lives_in_region' => 'I live in one of these states',
        'works_in_region' => 'I work in one of these states',
        'attends_school' => 'I attend school in one of these states',
        'worships_in_region' => 'I worship in one of these states',
        'family_member' => 'A family member is a {short_name} member',
    ];

    /** Accounts a new member can ask for when joining. */
    public const ACCOUNT_CHOICES = ['both' => 'Savings and checking', 'savings' => 'Savings only', 'checking' => 'Checking only'];

    public function create(array $input): User
    {
        $states = \App\Support\Region::codes();

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
            'account_choice' => ['nullable', Rule::in(array_keys(self::ACCOUNT_CHOICES))],
            'id_document' => ['required', 'file', 'mimes:png,jpg,jpeg,webp,pdf', 'max:8192'],
            'address_document' => ['required', 'file', 'mimes:png,jpg,jpeg,webp,pdf', 'max:8192'],
            'open_business' => ['nullable', 'boolean'],
            ...\App\Support\BusinessRules::rules('required_if:open_business,1'),
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()],
            'terms' => ['accepted'],
        ], [
            'dob.before_or_equal' => 'You must be at least 18 to open an account.',
            'eligibility_state.required_unless' => 'Choose the state that connects you to '.brand('short_name').'.',
            'eligibility_state.in' => 'We serve '.\App\Support\Region::sentence().'.',
            'referred_by_member_number.exists' => 'We could not find that member number.',
            'referred_by_member_number.required_if' => 'Enter your family member\'s member number.',
            ...\App\Support\BusinessRules::messages('required_if'),
            'id_document.required' => 'Upload a photo of your government ID.',
            'address_document.required' => 'Upload a document that shows your home address.',
            'id_document.mimes' => 'Use a PNG, JPG, WebP or PDF file.',
            'address_document.mimes' => 'Use a PNG, JPG, WebP or PDF file.',
            'id_document.max' => 'The file must be 8 MB or smaller.',
            'address_document.max' => 'The file must be 8 MB or smaller.',
            'id_document.uploaded' => 'That file could not be uploaded. Try a smaller one.',
            'address_document.uploaded' => 'That file could not be uploaded. Try a smaller one.',
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

            app(AccountOpening::class)->openChosen($user, $input['account_choice'] ?? 'both');

            $business = ! empty($input['open_business']);
            if ($business) {
                \App\Models\BusinessProfile::create([
                    'user_id' => $user->id, 'legal_name' => $input['business_name'], 'entity_type' => $input['business_type'], 'ein' => preg_replace('/\D/', '', $input['business_ein']),
                    'industry' => $input['business_industry'], 'started_year' => (int) $input['business_started_year'], 'address_line1' => $input['business_address_line1'],
                    'city' => $input['business_city'], 'state' => strtoupper($input['business_state']), 'postal_code' => $input['business_postal_code'],
                ]);
                app(AccountOpening::class)->openBusiness($user, $input['business_accounts']);
            }

            // Identity documents travel with the application. A bad image sends the form back and rolls the join back.
            $docs = ['id_document' => 'government_id', 'address_document' => 'proof_of_address'] + ($business ? ['business_document' => 'business_document'] : []);
            foreach ($docs as $field => $type) {
                try {
                    app(\App\Services\KycService::class)->submit($user, $type, $input[$field]);
                } catch (\Illuminate\Validation\ValidationException $e) {
                    throw \Illuminate\Validation\ValidationException::withMessages([$field => collect($e->errors())->flatten()->first()]);
                }
            }

            Audit::record('member.registered', $user, null, ['member_number' => $user->member_number], $user->id);

            return $user;
        });
    }
}
