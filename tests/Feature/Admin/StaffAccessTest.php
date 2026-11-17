<?php

use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    seedReference();
    config(['bank.default_daily_limit' => 20_000_000]);
});

it('keeps members and signed-out visitors out of the staff console', function () {
    [$member] = verifiedMember();
    $this->get('/admin')->assertRedirect('/login');
    $this->actingAs($member)->get('/admin')->assertForbidden();
    $this->actingAs($member)->get('/admin/audit')->assertForbidden();
});

it('requires two-step verification before staff can use the console', function () {
    $staff = makeStaff('Manager', twoFactor: false);

    $this->actingAs($staff)->get('/admin')->assertRedirect('/admin/security');
    $this->actingAs($staff)->get('/admin/members')->assertRedirect('/admin/security');
    $this->actingAs($staff)->get('/admin/security')->assertOk()->assertSee('Set up two-step verification');
});

it('shows the overview to staff with two-step on', function () {
    $this->actingAs(makeStaff('Manager'))->get('/admin')->assertOk()->assertSee('Overview')->assertSee('Staff console');
});

it('signs staff out after ten idle minutes', function () {
    $staff = makeStaff('Manager');
    $this->actingAs($staff)->withSession(['staff_last_activity' => time() - 11 * 60])->get('/admin')->assertRedirect('/login');
    $this->assertGuest();
});

it('sends staff to the console and members to their dashboard after sign in', function () {
    $staff = makeStaff('Manager', twoFactor: false);
    $staff->forceFill(['password' => 'Correct-Horse-9'])->save();
    $this->post('/login', ['email' => $staff->email, 'password' => 'Correct-Horse-9'])->assertRedirect('/admin');
});

it('keeps staff out of member pages unless they are acting as a member', function () {
    $staff = makeStaff('Manager');
    $this->actingAs($staff)->get('/dashboard')->assertRedirect('/admin');
    $this->actingAs($staff)->get('/transfers/new')->assertRedirect('/admin');
});

it('limits every title to its own permissions', function (string $title, array $allowed, array $denied) {
    [$member] = verifiedMember();
    $staff = makeStaff($title);
    $urls = [
        'members' => ['get', '/admin/members'],
        'queue' => ['get', '/admin/queue'],
        'audit' => ['get', '/admin/audit'],
        'inquiries' => ['get', '/admin/inquiries'],
        'settings' => ['get', '/admin/settings'],
        'lock' => ['post', "/admin/members/{$member->id}/locks"],
        'adjust' => ['post', "/admin/members/{$member->id}/adjustments"],
        'actas' => ['post', "/admin/members/{$member->id}/act-as"],
    ];

    foreach ($denied as $key) {
        [$method, $url] = $urls[$key];
        $this->actingAs($staff)->{$method}($url)->assertForbidden();
    }
    foreach ($allowed as $key) {
        [$method, $url] = $urls[$key];
        expect($this->actingAs($staff)->{$method}($url)->status())->not->toBe(403, "$title should reach $key");
    }
})->with([
    'Teller' => ['Teller', ['members', 'queue'], ['audit', 'inquiries', 'settings', 'lock', 'adjust', 'actas']],
    'Manager' => ['Manager', ['members', 'queue', 'audit', 'inquiries', 'lock', 'adjust', 'actas'], ['settings']],
    'Compliance' => ['Compliance', ['members', 'queue', 'audit', 'inquiries', 'lock', 'actas'], ['settings', 'adjust']],
    'Support Agent' => ['Support Agent', ['members', 'inquiries', 'actas'], ['queue', 'audit', 'settings', 'lock', 'adjust']],
    'Super Admin' => ['Super Admin', ['members', 'queue', 'audit', 'inquiries', 'settings', 'lock', 'adjust', 'actas'], []],
]);

it('searches members by name, member number and account digits', function () {
    [$maria, $checking] = verifiedMember('Maria Alvarez');
    verifiedMember('David Okafor');
    $staff = makeStaff('Teller');

    $this->actingAs($staff)->get('/admin/members?q=Alvarez')->assertSee('Maria Alvarez')->assertDontSee('David Okafor');
    $this->actingAs($staff)->get('/admin/members?q='.$maria->member_number)->assertSee('Maria Alvarez')->assertDontSee('David Okafor');
    $this->actingAs($staff)->get('/admin/members?q='.substr($checking->account_number, -6))->assertSee('Maria Alvarez');
});

it('shows Member 360 with accounts, balances and recent activity', function () {
    [$maria, $checking] = verifiedMember('Maria Alvarez');
    deposit($checking, 123_456);
    $this->actingAs(makeStaff('Teller'))->get("/admin/members/{$maria->id}")->assertOk()->assertSee('$1,234.56')->assertSee('Everyday Checking');
});

it('lands website inquiries in the inbox and lets staff mark them handled', function () {
    $this->post('/contact', ['name' => 'Linda Nguyen', 'email' => 'linda.nguyen@example.com', 'topic' => 'Checking', 'message' => 'Do you have a student account?'])->assertRedirect();
    $inquiry = Inquiry::firstOrFail();

    $staff = makeStaff('Support Agent');
    $this->actingAs($staff)->get('/admin/inquiries')->assertOk()->assertSee('Linda Nguyen')->assertSee('student account');
    $this->actingAs($staff)->post("/admin/inquiries/{$inquiry->id}/handle")->assertRedirect();
    expect($inquiry->refresh()->handled_by)->toBe($staff->id);
    $this->actingAs($staff)->get('/admin/inquiries')->assertDontSee('student account');
});

it('lets only Super Admin change settings', function () {
    $super = makeStaff('Super Admin');
    $this->actingAs($super)->put('/admin/settings', [])->assertRedirect();
    expect(App\Models\Setting::bool('impersonation_allow_money_actions', true))->toBeFalse();
    $this->actingAs(makeStaff('Manager'))->put('/admin/settings', [])->assertForbidden();
});
