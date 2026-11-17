<?php

use App\Models\AuditLog;
use App\Models\Decision;
use App\Models\KycDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    seedReference();
    Storage::fake('local');
});

function upload(App\Models\User $member, string $type, ?UploadedFile $file = null)
{
    return test()->actingAs($member)->post('/verification', ['type' => $type, 'file' => $file ?? UploadedFile::fake()->image('id.jpg', 800, 500)]);
}

it('lets a member upload both documents and waits for review', function () {
    [$member] = verifiedMember();

    upload($member, 'government_id')->assertRedirect('/verification');
    upload($member, 'proof_of_address', UploadedFile::fake()->createWithContent('bill.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF"))->assertRedirect('/verification');

    expect(KycDocument::count())->toBe(2)->and($member->refresh()->kyc_status)->toBe('pending');
    $doc = KycDocument::where('type', 'government_id')->first();
    expect($doc->path)->toStartWith('private/kyc/');
    Storage::disk('local')->assertExists($doc->path);
    $this->actingAs($member)->get('/verification')->assertSee('In review');
});

it('refuses documents that are not images or real PDFs', function () {
    [$member] = verifiedMember();

    upload($member, 'government_id', UploadedFile::fake()->createWithContent('id.png', '<svg xmlns="http://www.w3.org/2000/svg"><script>1</script></svg>'))->assertSessionHasErrors('file');
    upload($member, 'government_id', UploadedFile::fake()->createWithContent('id.pdf', 'MZ this is an executable'))->assertSessionHasErrors('file');
    upload($member, 'selfie')->assertSessionHasErrors('type');
    expect(KycDocument::count())->toBe(0);
});

it('verifies a member once both documents are approved', function () {
    [$member] = verifiedMember();
    upload($member, 'government_id');
    upload($member, 'proof_of_address');
    $compliance = makeStaff('Compliance');

    $first = KycDocument::where('type', 'government_id')->first();
    $this->actingAs($compliance)->post("/admin/identity/{$first->id}/approve")->assertRedirect();
    expect($member->refresh()->kyc_status)->toBe('pending');

    $second = KycDocument::where('type', 'proof_of_address')->first();
    $this->actingAs($compliance)->post("/admin/identity/{$second->id}/approve")->assertRedirect();

    expect($member->refresh()->kyc_status)->toBe('verified')->and(Decision::where('decidable_type', 'kyc_document')->orWhere('decidable_type', KycDocument::class)->count())->toBe(2);
    expect(AuditLog::where('action', 'kyc.approved')->count())->toBe(2)->and(AuditLog::where('action', 'kyc.status_changed')->latest('id')->first()->new)->toBe(['status' => 'verified']);
    $this->actingAs($member)->get('/verification')->assertSee('You are verified');
});

it('shows the member the reason for a rejection and replaces the document on a new upload', function () {
    [$member] = verifiedMember();
    upload($member, 'government_id');
    $doc = KycDocument::first();

    $this->actingAs(makeStaff('Compliance'))->post("/admin/identity/{$doc->id}/reject", ['reason_code' => 'unclear_image', 'reason_text' => 'The photo is too dark to read.'])->assertRedirect();
    expect($member->refresh()->kyc_status)->toBe('rejected');
    $this->actingAs($member)->get('/verification')->assertSee('The photo is too dark to read.')->assertSee('Not accepted');

    upload($member, 'government_id')->assertRedirect('/verification');
    expect($doc->refresh()->status)->toBe('replaced')->and(KycDocument::where('status', 'pending')->count())->toBe(1)->and($member->refresh()->kyc_status)->toBe('pending');
});

it('requires a reason to reject', function () {
    [$member] = verifiedMember();
    upload($member, 'government_id');
    $doc = KycDocument::first();

    $this->actingAs(makeStaff('Compliance'))->post("/admin/identity/{$doc->id}/reject", ['reason_code' => 'unclear_image', 'reason_text' => ''])->assertSessionHasErrors('reason_text');
    expect($doc->refresh()->status)->toBe('pending');
});

it('lets only staff with the KYC permission review, and shows documents only to the owner and reviewers', function () {
    [$member] = verifiedMember();
    [$other] = verifiedMember('Sam Rivera');
    upload($member, 'government_id');
    $doc = KycDocument::first();

    foreach (['Teller', 'Manager', 'Support Agent'] as $title) {
        $this->actingAs(makeStaff($title))->get('/admin/identity')->assertForbidden();
        $this->actingAs(makeStaff($title))->post("/admin/identity/{$doc->id}/approve")->assertForbidden();
        $this->actingAs(makeStaff($title))->get("/files/kyc/{$doc->id}")->assertForbidden();
    }
    $this->actingAs($other)->get("/files/kyc/{$doc->id}")->assertForbidden();
    $this->actingAs($member)->get("/files/kyc/{$doc->id}")->assertOk();

    $compliance = makeStaff('Compliance');
    $this->actingAs($compliance)->get('/admin/identity')->assertOk()->assertSee($member->name);
    $this->actingAs($compliance)->get("/admin/identity/{$doc->id}")->assertOk();
    $this->actingAs($compliance)->get("/files/kyc/{$doc->id}")->assertOk();
    expect(AuditLog::where('action', 'kyc.document_viewed')->where('actor_id', $compliance->id)->count())->toBe(1);
});

it('does not let a decided document be decided again', function () {
    [$member] = verifiedMember();
    upload($member, 'government_id');
    $doc = KycDocument::first();
    $compliance = makeStaff('Compliance');
    $this->actingAs($compliance)->post("/admin/identity/{$doc->id}/approve");

    $this->actingAs($compliance)->post("/admin/identity/{$doc->id}/reject", ['reason_code' => 'unclear_image', 'reason_text' => 'Changed my mind'])->assertSessionHasErrors('action');
    expect($doc->refresh()->status)->toBe('approved');
});

it('lets a verified member deposit a check', function () {
    [$member, $checking] = verifiedMember();
    upload($member, 'government_id');
    upload($member, 'proof_of_address');
    $compliance = makeStaff('Compliance');
    foreach (KycDocument::all() as $d) {
        $this->actingAs($compliance)->post("/admin/identity/{$d->id}/approve");
    }

    $this->actingAs($member->refresh())->post('/deposits/check', [
        'token' => (string) Illuminate\Support\Str::ulid(), 'account_id' => $checking->id, 'amount' => '55.00',
        'front' => UploadedFile::fake()->image('f.jpg', 640, 300), 'back' => UploadedFile::fake()->image('b.jpg', 640, 300),
    ])->assertRedirect('/deposits')->assertSessionHas('status');
});
