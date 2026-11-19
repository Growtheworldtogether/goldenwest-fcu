<?php

use App\Enums\TransactionType;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Ledger\TransferRequest;
use App\Services\Ledger\TransferService;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => seedReference());

function removeMember(User $staff, User $member, array $extra = [])
{
    return test()->actingAs($staff)->withSession(['auth.password_confirmed_at' => time()])
        ->post("/admin/members/{$member->id}/remove", array_merge(['member_number' => $member->member_number, 'reason' => 'Requested by the member'], $extra));
}

it('erases a member who never used their account, with their accounts, and keeps the audit rows', function () {
    [$member, $checking] = verifiedMember();
    $id = $member->id;
    AuditLog::create(['actor_id' => $id, 'action' => 'login.success', 'subject_type' => 'User', 'subject_id' => $id]);

    removeMember(makeStaff('Super Admin'), $member)->assertRedirect('/admin/members')->assertSessionHas('toast.message', 'Member erased. They had never used their account.');

    expect(User::find($id))->toBeNull()->and(App\Models\Account::find($checking->id))->toBeNull();
    expect(AuditLog::where('action', 'login.success')->value('actor_id'))->toBeNull()->and(AuditLog::where('action', 'member.erased')->exists())->toBeTrue();
});

it('closes a member who has money history: sign in off, details wiped, records kept', function () {
    [$member, $checking] = verifiedMember('Maria Alvarez');
    deposit($checking, 50_000);
    app(TransferService::class)->execute(new TransferRequest(type: TransactionType::Withdrawal, amount: Money::cents(50_000), idempotencyKey: 'w:'.uniqid(), fromAccountId: $checking->id, initiatedBy: $member->id));
    $member->forceFill(['phone' => '555-0100', 'address_line1' => '1 Main St'])->save();

    removeMember(makeStaff('Super Admin'), $member)->assertSessionHas('toast.message', fn ($m) => str_contains($m, 'Member closed'));

    $m = $member->refresh();
    expect($m->status)->toBe('closed')->and($m->name)->toBe('Closed member '.$m->member_number)->and($m->email)->toEndWith('@closed.invalid')
        ->and($m->phone)->toBeNull()->and($m->address_line1)->toBeNull();
    expect($checking->refresh()->status)->toBe('closed')->and(App\Models\Transaction::where('from_account_id', $checking->id)->exists())->toBeTrue();
    auth()->logout();
    $this->flushSession();
    $this->post('/login', ['email' => 'closed-'.$m->id.'@closed.invalid', 'password' => 'a-long-test-password-1'])->assertSessionHasErrors();
    $this->assertGuest();
});

it('refuses while an account still has money, and for the wrong member number', function () {
    [$member, $checking] = verifiedMember();
    deposit($checking, 10_000);
    $super = makeStaff('Super Admin');
    removeMember($super, $member)->assertSessionHas('toast.type', 'bad');
    expect(User::find($member->id))->not->toBeNull();

    [$other] = verifiedMember('Lena Ortiz');
    removeMember($super, $other, ['member_number' => '0000000'])->assertSessionHas('toast.message', 'The member number you typed does not match.');
    expect(User::find($other->id))->not->toBeNull();
});

it('is for a Super Admin only, and never for staff', function () {
    [$member] = verifiedMember();
    removeMember(makeStaff('Manager'), $member)->assertForbidden();
    expect(User::find($member->id))->not->toBeNull();
    $super = makeStaff('Super Admin');
    $staff = makeStaff('Teller', name: 'Tess Teller');
    removeMember($super, $staff)->assertNotFound();
});

it('shows the delete button to a Super Admin only', function () {
    [$member] = verifiedMember();
    $this->actingAs(makeStaff('Super Admin'))->get("/admin/members/{$member->id}")->assertOk()->assertSee('Delete member')->assertSee('erased for good');
    $this->actingAs(makeStaff('Manager'))->get("/admin/members/{$member->id}")->assertOk()->assertDontSee('Delete member');
});

it('erases a real new sign-up, with their profile, documents and decisions', function () {
    Illuminate\Support\Facades\Storage::fake('local');
    $this->post('/register', registrationData())->assertSessionHasNoErrors();
    $member = User::where('email', 'linda.nguyen@example.com')->firstOrFail();
    $member->forceFill(['email_verified_at' => now()])->save();
    expect(App\Models\KycDocument::where('user_id', $member->id)->count())->toBe(2);
    auth()->logout();
    $this->flushSession();

    removeMember(makeStaff('Super Admin'), $member)->assertSessionHas('toast.message', 'Member erased. They had never used their account.');
    expect(User::find($member->id))->toBeNull()->and(App\Models\KycDocument::count())->toBe(0)->and(App\Models\MemberProfile::count())->toBe(0);
});
