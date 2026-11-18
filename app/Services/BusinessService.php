<?php

namespace App\Services;

use App\Models\Account;
use App\Models\BusinessProfile;
use App\Models\User;
use App\Notifications\MemberNotice;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** A member who is already a member adds a business. The accounts open when staff approve the business document. */
final class BusinessService
{
    public function __construct(private KycService $kyc, private AccountOpening $opening)
    {
    }

    public function request(User $member, array $d, UploadedFile $document): BusinessProfile
    {
        if ($member->kyc_status !== 'verified') {
            throw ValidationException::withMessages(['business_name' => 'Please verify your identity before you add a business.']);
        }
        if ($member->businessProfile()->exists()) {
            throw ValidationException::withMessages(['business_name' => 'You already have a business with us.']);
        }

        return DB::transaction(function () use ($member, $d, $document) {
            $profile = BusinessProfile::create([
                'user_id' => $member->id, 'status' => 'pending', 'requested_accounts' => $d['business_accounts'],
                'legal_name' => $d['business_name'], 'entity_type' => $d['business_type'], 'ein' => preg_replace('/\D/', '', $d['business_ein']),
                'industry' => $d['business_industry'], 'started_year' => (int) $d['business_started_year'], 'address_line1' => $d['business_address_line1'],
                'city' => $d['business_city'], 'state' => strtoupper($d['business_state']), 'postal_code' => $d['business_postal_code'],
            ]);
            try {
                $this->kyc->submit($member, 'business_document', $document);
            } catch (ValidationException $e) {
                throw ValidationException::withMessages(['business_document' => collect($e->errors())->flatten()->first()]);
            }
            Audit::record('business.requested', $profile, null, ['accounts' => $d['business_accounts']], $member->id);

            return $profile;
        });
    }

    /** The business document was approved: open what the member asked for. Does nothing if it was already done. */
    public function activate(User $member, User $staff): void
    {
        $profile = $member->businessProfile;
        if (! $profile || ! $profile->isPending()) {
            return;
        }
        $profile->update(['status' => 'approved']);
        $this->opening->openBusiness($member, $profile->requested_accounts ?: 'both');
        Audit::record('business.approved', $profile, ['status' => 'pending'], ['status' => 'approved'], $staff->id);
        MemberNotice::send($member, 'Your business accounts are open', ['We approved your business document and opened the business accounts for '.$profile->legal_name.'.', 'Sign in to see your account numbers.'], 'See my accounts', route('dashboard'));
    }

    /** Business product codes the member does not have yet. */
    public function missing(User $member): array
    {
        $have = $member->accounts()->with('product')->get()->pluck('product.code')->all();

        return array_values(array_diff(['BUSINESS-CHECKING', 'BUSINESS-SAVINGS'], $have));
    }

    public function openMore(User $member, string $code): Account
    {
        $profile = $member->businessProfile;
        if (! $profile || $profile->isPending()) {
            throw ValidationException::withMessages(['account' => 'Your business has not been approved yet.']);
        }
        if (! in_array($code, $this->missing($member), true)) {
            throw ValidationException::withMessages(['account' => 'You already have that account.']);
        }
        $account = $this->opening->open($member, $code);
        $account->update(['daily_limit' => (int) config('bank.business_daily_limit')]);

        return $account;
    }
}
