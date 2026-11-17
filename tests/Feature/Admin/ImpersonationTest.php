<?php

use App\Models\AuditLog;
use App\Models\Impersonation;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    seedReference();
    config(['bank.default_daily_limit' => 50_000_000]);
});

function startActing(User $staff, User $member, array $extra = [])
{
    return test()->actingAs($staff)->post("/admin/members/{$member->id}/act-as", array_merge(['reason_code' => 'member_support', 'ticket' => 'T-1042'], $extra));
}

function transferPayload($from, $to, string $amount = '25.00', array $extra = []): array
{
    return array_merge(['token' => (string) Str::ulid(), 'from_account_id' => $from->id, 'to_account_id' => $to->id, 'amount' => $amount], $extra);
}

it('needs a reason, then shows the member view with a permanent banner', function () {
    [$member, $checking] = verifiedMember('Maria Alvarez');
    $manager = makeStaff('Manager', name: 'Daniel Brooks');

    $this->actingAs($manager)->post("/admin/members/{$member->id}/act-as", [])->assertSessionHasErrors('reason_code');
    startActing($manager, $member)->assertRedirect('/dashboard');

    $this->get('/dashboard')->assertOk()->assertSee('Acting as Maria Alvarez')->assertSee('staff: Daniel Brooks')->assertSee('Exit');
    $row = Impersonation::firstOrFail();
    expect($row->admin_id)->toBe($manager->id)->and($row->member_id)->toBe($member->id)->and($row->ticket_reference)->toBe('T-1042')
        ->and($row->started_at->diffInMinutes($row->expires_at))->toBeGreaterThanOrEqual(29);
    expect(AuditLog::where('action', 'impersonation.started')->exists())->toBeTrue();
});

it('does not count the switch as a member sign in', function () {
    [$member] = verifiedMember();
    startActing(makeStaff('Manager'), $member);
    expect($member->refresh()->last_login_at)->toBeNull()->and(AuditLog::where('action', 'login.success')->count())->toBe(0);
});

it('refuses to act as another staff member, as yourself or as an inactive member', function () {
    $manager = makeStaff('Manager');
    $other = makeStaff('Teller');
    $this->actingAs($manager)->post("/admin/members/{$other->id}/act-as", ['reason_code' => 'member_support'])->assertForbidden();

    [$locked] = verifiedMember();
    $locked->forceFill(['locked_until' => now()->addDay()])->save();
    $this->actingAs($manager)->post("/admin/members/{$locked->id}/act-as", ['reason_code' => 'member_support'])->assertSessionHasErrors('reason_code');
});

it('requires the staff members own code before money moves and stamps the staff id on everything', function () {
    [$member, $checking, $savings] = verifiedMember();
    deposit($checking, 100_000);
    $manager = makeStaff('Manager');
    startActing($manager, $member);

    $this->post('/transfers', transferPayload($checking, $savings))->assertSessionHasErrors('admin_code');
    $this->post('/transfers', transferPayload($checking, $savings, extra: ['admin_code' => '000000']))->assertSessionHasErrors('admin_code');
    expect($savings->refresh()->balance->cents)->toBe(0);

    $this->post('/transfers', transferPayload($checking, $savings, extra: ['admin_code' => staffCode()]))->assertRedirect();
    $tx = Transaction::latest('id')->first();
    expect($tx->initiated_by)->toBe($member->id)->and($tx->impersonator_id)->toBe($manager->id)->and($savings->refresh()->balance->cents)->toBe(2_500);
    expect(AuditLog::where('subject_id', $tx->id)->where('impersonator_id', $manager->id)->exists())->toBeTrue();
});

it('asks staff without two-step for their password instead before money moves', function () {
    [$member, $checking, $savings] = verifiedMember();
    deposit($checking, 100_000);
    $staff = makeStaff('Manager', twoFactor: false);
    $staff->forceFill(['password' => 'Staff-Password-77'])->save();
    startActing($staff, $member);

    $this->get('/transfers/new')->assertSee('Your staff password');
    $this->post('/transfers', transferPayload($checking, $savings))->assertSessionHasErrors('admin_code');
    $this->post('/transfers', transferPayload($checking, $savings, extra: ['admin_code' => 'wrong-password']))->assertSessionHasErrors('admin_code');
    expect($savings->refresh()->balance->cents)->toBe(0);

    $this->post('/transfers', transferPayload($checking, $savings, extra: ['admin_code' => 'Staff-Password-77']))->assertRedirect();
    expect($savings->refresh()->balance->cents)->toBe(2_500)->and(Transaction::latest('id')->first()->impersonator_id)->toBe($staff->id);
});

it('stamps the staff id on every audit row written during the session', function () {
    [$member, $checking, $savings] = verifiedMember();
    deposit($checking, 100_000);
    startActing(makeStaff('Manager'), $member);
    $before = AuditLog::max('id');
    $this->post('/accounts/open', ['product' => 'MONEY-MARKET', 'nickname' => 'Rainy day']);

    $new = AuditLog::where('id', '>', $before)->get();
    expect($new)->not->toBeEmpty()->and($new->every(fn ($r) => $r->impersonator_id !== null))->toBeTrue();
});

it('blocks a staff member from approving what they started while acting as the member', function () {
    [$member, $checking, $savings] = verifiedMember();
    deposit($checking, 2_000_000);
    $manager = makeStaff('Manager');
    startActing($manager, $member);
    $this->post('/transfers', transferPayload($checking, $savings, '6000.00', ['admin_code' => staffCode()]));
    $tx = Transaction::latest('id')->first();
    expect($tx->status->value)->toBe('pending');

    $this->post('/exit-member');
    $this->actingAs($manager)->post("/admin/queue/{$tx->reference}/approve")->assertSessionHasErrors('action');
    expect($tx->refresh()->status->value)->toBe('pending');
    $this->actingAs(makeStaff('Compliance', name: 'Rachel Kim'))->post("/admin/queue/{$tx->reference}/approve");
    expect($tx->refresh()->status->value)->toBe('completed');
});

it('blocks password, contact and two-step changes while acting', function () {
    [$member] = verifiedMember();
    $member->forceFill(['password' => 'Correct-Horse-9'])->save();
    startActing(makeStaff('Manager'), $member);

    $this->put('/user/password', ['current_password' => 'Correct-Horse-9', 'password' => 'Another-Pass-77', 'password_confirmation' => 'Another-Pass-77'])->assertForbidden();
    $this->put('/user/profile-information', ['phone' => '408-555-0100', 'address_line1' => '1 A St', 'city' => 'San Jose', 'state' => 'CA', 'postal_code' => '95110'])->assertForbidden();
    $this->post('/user/two-factor-authentication')->assertForbidden();
    expect($member->refresh()->two_factor_secret)->toBeNull();
});

it('lets a Super Admin switch money actions off', function () {
    [$member, $checking, $savings] = verifiedMember();
    deposit($checking, 100_000);
    Setting::put('impersonation_allow_money_actions', false);
    startActing(makeStaff('Manager'), $member);

    $this->post('/transfers', transferPayload($checking, $savings, extra: ['admin_code' => staffCode()]))->assertForbidden();
    expect($savings->refresh()->balance->cents)->toBe(0);
});

it('ends after 30 minutes and returns to the staff console', function () {
    [$member] = verifiedMember();
    $manager = makeStaff('Manager');
    startActing($manager, $member);

    $this->travel(31)->minutes();
    $this->get('/dashboard')->assertRedirect('/admin');
    expect(Impersonation::first()->ended_reason)->toBe('expired');
    $this->assertAuthenticatedAs($manager);
    $this->travelBack();
});

it('exits on request, records the end and returns the staff member', function () {
    [$member] = verifiedMember();
    $manager = makeStaff('Manager');
    startActing($manager, $member);

    $this->post('/exit-member')->assertRedirect('/admin');
    expect(Impersonation::first()->ended_reason)->toBe('exited')->and(AuditLog::where('action', 'impersonation.ended')->exists())->toBeTrue();
    $this->assertAuthenticatedAs($manager);
    $this->get('/dashboard')->assertRedirect('/admin');
});

it('tells the member that staff acted on their account', function () {
    [$member] = verifiedMember('Maria Alvarez');
    $manager = makeStaff('Manager', name: 'Daniel Brooks');
    startActing($manager, $member);
    $this->post('/exit-member');

    $this->actingAs($member)->get('/security-settings')->assertOk()->assertSee('Staff accessed or acted on your account')->assertSee('Daniel Brooks')->assertSee('Helping the member with a request');
});

it('lists acting sessions in the audit viewer', function () {
    [$member, $checking] = verifiedMember();
    $manager = makeStaff('Manager');
    startActing($manager, $member);
    $this->post('/accounts/open', ['product' => 'MONEY-MARKET']);
    $this->post('/exit-member');

    $this->actingAs($manager)->get('/admin/audit?acting=1')->assertOk()->assertSee('account.opened')->assertDontSee('login.success');
});
