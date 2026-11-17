<?php

namespace Database\Seeders;

use App\Models\ReasonCode;
use Illuminate\Database\Seeder;

class ReasonCodeSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['unusual_activity', 'Activity differs from your usual pattern', 'decision'],
            ['verification_required', 'We need to verify your identity or the payee', 'decision'],
            ['suspected_fraud', 'Possible fraud on the account', 'all'],
            ['over_limit', 'The amount is over your limit', 'decision'],
            ['insufficient_documentation', 'We need more documentation', 'decision'],
            ['account_review', 'The account is under review', 'all'],
            ['regulatory_review', 'Required review', 'all'],
            ['duplicate_request', 'This looks like a duplicate request', 'decision'],
            ['member_request', 'Requested by the member', 'all'],
            ['error_correction', 'Correction of an error', 'adjustment'],
            ['goodwill', 'Goodwill credit', 'adjustment'],
            ['member_support', 'Helping the member with a request', 'impersonation'],
            ['identity_assist', 'Helping the member regain access', 'impersonation'],
            ['investigation', 'Reviewing an account concern', 'impersonation'],
            ['unclear_image', 'The image is blurry, cropped or hard to read', 'decision'],
            ['name_mismatch', 'The name does not match our records', 'decision'],
            ['expired_document', 'The document has expired', 'decision'],
            ['deceased_member', 'Member reported deceased', 'restriction'],
        ] as [$code, $label, $applies]) {
            ReasonCode::updateOrCreate(['code' => $code], ['label' => $label, 'applies_to' => $applies]);
        }
    }
}
