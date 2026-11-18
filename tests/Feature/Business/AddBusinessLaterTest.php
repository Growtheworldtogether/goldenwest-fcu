<?php

use App\Models\BusinessProfile;
use App\Models\KycDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

beforeEach(fn () => seedReference());

function businessForm(array $o = []): array
{
    return array_merge([
        'business_name' => 'Stone Landscaping LLC', 'business_type' => 'llc', 'business_ein' => '12-3456789', 'business_industry' => 'Landscaping', 'business_started_year' => '2019',
        'business_address_line1' => '40 Pine St', 'business_city' => 'Oakland', 'business_state' => 'ca', 'business_postal_code' => '94607', 'business_accounts' => 'both',
        'business_document' => UploadedFile::fake()->image('articles.jpg', 800, 1000),
    ], $o);
}

function verifiedOwner(): User
{
    [$u] = verifiedMember('Ben Ortiz');
    $u->forceFill(['kyc_status' => 'verified'])->save();

    return $u->refresh();
}

it('lets a verified member send a business for review without opening anything yet', function () {
    $u = verifiedOwner();
    $before = $u->accounts()->count();
    $this->actingAs($u)->post('/my-business', businessForm())->assertRedirect('/my-business');
    $p = $u->fresh()->businessProfile;
    expect($p->status)->toBe('pending')->and($p->requested_accounts)->toBe('both')->and($p->state)->toBe('CA');
    expect($u->accounts()->count())->toBe($before)->and($u->fresh()->kyc_status)->toBe('verified');
    expect(KycDocument::where('user_id', $u->id)->where('type', 'business_document')->where('status', 'pending')->exists())->toBeTrue();
    $this->get('/my-business')->assertOk()->assertSee('We are reviewing your business document');
});

it('opens the requested business accounts when staff approve the document', function () {
    $u = verifiedOwner();
    $this->actingAs($u)->post('/my-business', businessForm(['business_accounts' => 'checking']));
    $doc = KycDocument::where('user_id', $u->id)->where('type', 'business_document')->first();
    app(\App\Services\KycService::class)->decide($doc, makeStaff('Compliance'), true);
    $codes = $u->fresh()->accounts()->with('product')->get()->pluck('product.code')->all();
    expect($codes)->toContain('BUSINESS-CHECKING')->not->toContain('BUSINESS-SAVINGS');
    expect($u->fresh()->businessProfile->status)->toBe('approved')->and($u->fresh()->kyc_status)->toBe('verified');
    $acct = $u->accounts()->whereHas('product', fn ($q) => $q->where('code', 'BUSINESS-CHECKING'))->first();
    expect($acct->daily_limit->cents)->toBe(5_000_000);
});

it('keeps the member verified when the business document is turned down, and lets them upload again', function () {
    $u = verifiedOwner();
    $this->actingAs($u)->post('/my-business', businessForm());
    $doc = KycDocument::where('user_id', $u->id)->where('type', 'business_document')->first();
    app(\App\Services\KycService::class)->decide($doc, makeStaff('Compliance'), false, 'unclear_image', 'We cannot read the business name.');
    expect($u->fresh()->kyc_status)->toBe('verified')->and($u->fresh()->businessProfile->status)->toBe('pending');
    $this->get('/verification')->assertOk()->assertSee('Business document');
    $this->post('/verification', ['type' => 'business_document', 'file' => UploadedFile::fake()->image('new.jpg', 800, 1000)])->assertRedirect('/verification');
    $new = KycDocument::where('user_id', $u->id)->where('type', 'business_document')->where('status', 'pending')->first();
    app(\App\Services\KycService::class)->decide($new, makeStaff('Compliance'), true);
    expect($u->fresh()->businessProfile->status)->toBe('approved')->and($u->accounts()->count())->toBeGreaterThan(2);
});

it('needs a verified member, one business at most, and every field', function () {
    [$new] = verifiedMember('Unverified Person');
    $this->actingAs($new)->post('/my-business', businessForm())->assertSessionHasErrors('business_name');
    $u = verifiedOwner();
    $this->actingAs($u)->post('/my-business', businessForm(['business_ein' => '12-34']))->assertSessionHasErrors('business_ein');
    $this->post('/my-business', businessForm(['business_document' => null]))->assertSessionHasErrors('business_document');
    $this->post('/my-business', businessForm(['business_name' => '']))->assertSessionHasErrors('business_name');
    $this->post('/my-business', businessForm());
    $this->post('/my-business', businessForm())->assertSessionHasErrors('business_name');
    expect(BusinessProfile::count())->toBe(1);
});

it('lets an approved business open the account it left out', function () {
    $u = verifiedOwner();
    $this->actingAs($u)->post('/my-business', businessForm(['business_accounts' => 'checking']));
    app(\App\Services\KycService::class)->decide(KycDocument::where('user_id', $u->id)->where('type', 'business_document')->first(), makeStaff('Compliance'), true);
    $this->actingAs($u->fresh())->get('/my-business')->assertOk()->assertSee('Open another business account');
    $this->post('/my-business/accounts', ['product' => 'BUSINESS-SAVINGS'])->assertRedirect();
    expect($u->fresh()->accounts()->with('product')->get()->pluck('product.code')->all())->toContain('BUSINESS-SAVINGS');
    $this->post('/my-business/accounts', ['product' => 'BUSINESS-SAVINGS'])->assertSessionHasErrors('account');
});

it('shows the Add a business page and a link to it', function () {
    $u = verifiedOwner();
    $this->actingAs($u)->get('/my-business')->assertOk()->assertSee('Add a business')->assertSee('Send for review');
    $this->get('/accounts/open')->assertOk()->assertSee('Add a business and open business accounts');
});
