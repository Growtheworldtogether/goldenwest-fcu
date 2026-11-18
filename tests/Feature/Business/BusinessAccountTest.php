<?php

use App\Models\Account;
use App\Models\BusinessProfile;
use App\Models\KycDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

beforeEach(fn () => seedReference());

function businessJoin(array $o = []): array
{
    return registrationData(array_merge([
        'open_business' => '1', 'business_name' => 'Stone Landscaping LLC', 'business_type' => 'llc', 'business_ein' => '12-3456789',
        'business_industry' => 'Landscaping', 'business_started_year' => '2019', 'business_address_line1' => '40 Pine St', 'business_city' => 'Oakland',
        'business_state' => 'ca', 'business_postal_code' => '94607', 'business_accounts' => 'both',
        'business_document' => UploadedFile::fake()->image('articles.jpg', 800, 1000),
    ], $o));
}

it('opens business checking and savings under the owner at joining', function () {
    $this->post('/register', businessJoin())->assertRedirect('/welcome');
    $user = User::firstWhere('email', 'linda.nguyen@example.com');
    $accounts = $user->accounts()->with('product')->get();
    expect($accounts->pluck('product.code')->sort()->values()->all())->toBe(['BUSINESS-CHECKING', 'BUSINESS-SAVINGS', 'EVERYDAY-CHECKING', 'GOLDEN-SAVINGS']);
    $biz = $accounts->firstWhere('product.code', 'BUSINESS-CHECKING');
    expect($biz->daily_limit->cents)->toBe(5_000_000)->and($biz->overdraft_account_id)->toBeNull();
    $p = $user->businessProfile;
    expect($p->legal_name)->toBe('Stone Landscaping LLC')->and($p->state)->toBe('CA')->and($p->ein)->toBe('123456789')->and($p->maskedEin())->toBe('••-•••6789');
});

it('stores the EIN encrypted and never in the audit log', function () {
    $this->post('/register', businessJoin());
    expect(\Illuminate\Support\Facades\DB::table('business_profiles')->value('ein'))->not->toContain('123456789');
    expect(\App\Models\AuditLog::all()->contains(fn ($l) => str_contains(json_encode($l->getAttributes()), '123456789')))->toBeFalse();
});

it('opens only the business accounts that were asked for', function () {
    $this->post('/register', businessJoin(['business_accounts' => 'savings']));
    $codes = User::firstWhere('email', 'linda.nguyen@example.com')->accounts()->with('product')->get()->pluck('product.code')->all();
    expect($codes)->toContain('BUSINESS-SAVINGS')->not->toContain('BUSINESS-CHECKING');
});

it('needs the business details, the document and a real EIN', function () {
    $this->post('/register', businessJoin(['business_name' => '']))->assertSessionHasErrors('business_name');
    $this->post('/register', businessJoin(['business_document' => null]))->assertSessionHasErrors('business_document');
    $this->post('/register', businessJoin(['business_ein' => '12-345']))->assertSessionHasErrors('business_ein');
    $this->post('/register', businessJoin(['business_started_year' => now()->year + 1]))->assertSessionHasErrors('business_started_year');
    expect(User::where('email', 'linda.nguyen@example.com')->exists())->toBeFalse();
});

it('does not ask for business details when no business account is wanted', function () {
    $this->post('/register', registrationData())->assertRedirect('/welcome');
    $user = User::firstWhere('email', 'linda.nguyen@example.com');
    expect($user->businessProfile)->toBeNull();
    expect(KycDocument::where('user_id', $user->id)->pluck('type')->sort()->values()->all())->toBe(['government_id', 'proof_of_address']);
});

it('is verified only when the business document is approved too', function () {
    $this->post('/register', businessJoin());
    $user = User::firstWhere('email', 'linda.nguyen@example.com');
    expect(KycDocument::where('user_id', $user->id)->pluck('type')->sort()->values()->all())->toBe(['business_document', 'government_id', 'proof_of_address']);
    $staff = makeStaff('Compliance');
    $svc = app(\App\Services\KycService::class);
    foreach (['government_id', 'proof_of_address'] as $t) {
        $svc->decide(KycDocument::where('user_id', $user->id)->where('type', $t)->first(), $staff, true);
    }
    expect($user->fresh()->kyc_status)->toBe('pending');
    $svc->decide(KycDocument::where('user_id', $user->id)->where('type', 'business_document')->first(), $staff, true);
    expect($user->fresh()->kyc_status)->toBe('verified');
});

it('shows the business on the welcome page and to staff', function () {
    $this->post('/register', businessJoin());
    $this->get('/welcome')->assertSee('Business checking', false)->assertSee('Stone Landscaping LLC', false);
    $user = User::firstWhere('email', 'linda.nguyen@example.com');
    $this->post('/logout');
    $this->actingAs(makeStaff('Manager'))->get('/admin/members/'.$user->id)->assertOk()->assertSee('Stone Landscaping LLC', false)->assertSee('••-•••6789', false)->assertDontSee('123456789', false);
});

it('keeps personal and business overdraft protection apart', function () {
    $this->post('/register', businessJoin());
    $user = User::firstWhere('email', 'linda.nguyen@example.com');
    $user->forceFill(['email_verified_at' => now()])->save();
    $accounts = $user->accounts()->with('product')->get()->keyBy('product.code');
    $this->actingAs($user->fresh())->put('/overdraft/'.$accounts['BUSINESS-CHECKING']->id, ['source_id' => $accounts['GOLDEN-SAVINGS']->id])->assertSessionHas('toast');
    expect($accounts['BUSINESS-CHECKING']->fresh()->overdraft_account_id)->toBeNull();
    $this->put('/overdraft/'.$accounts['BUSINESS-CHECKING']->id, ['source_id' => $accounts['BUSINESS-SAVINGS']->id]);
    expect($accounts['BUSINESS-CHECKING']->fresh()->overdraft_account_id)->toBe($accounts['BUSINESS-SAVINGS']->id);
});
