<?php

use App\Models\User;
use App\Notifications\MemberNotice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(fn () => seedReference());

it('emails a member when a new device signs in, but not on the first sign-in or a repeat', function () {
    Notification::fake();
    [$member] = verifiedMember('Maria Alvarez');
    $login = fn (string $agent) => $this->withHeader('User-Agent', $agent)->post('/login', ['email' => $member->email, 'password' => 'a-long-test-password-1']);

    $login('Mozilla/5.0 (Windows NT 10.0) Chrome/120.0');
    Notification::assertNothingSent();
    $this->post('/logout');
    $login('Mozilla/5.0 (Windows NT 10.0) Chrome/121.0');
    Notification::assertNothingSent();
    $this->post('/logout');
    $login('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0) Safari/605.1');
    Notification::assertSentTo($member, MemberNotice::class, fn ($n) => $n->security && str_contains($n->subject, 'New sign-in'));
});

it('sends security notices even when the member turned email off, and skips the rest', function () {
    Notification::fake();
    [$member] = verifiedMember('Maria Alvarez');
    $member->forceFill(['notification_preferences' => ['email_transactions' => false]])->save();

    MemberNotice::send($member, 'Activity', ['Something happened.']);
    Notification::assertNothingSent();

    $notice = new MemberNotice('Security', ['Check this.'], null, null, true);
    expect($notice->via($member))->toBe(['mail']);
    expect((new MemberNotice('Activity', ['x']))->via($member))->toBe([]);
});

it('keeps amounts out of the email body', function () {
    [$member] = verifiedMember('Maria Alvarez');
    $mail = (new MemberNotice('Your loan application LA1 was approved', ['Good news.'], 'See your loan', 'https://example.test/x'))->toMail($member);
    expect($mail->subject)->toContain('approved')->and(implode(' ', $mail->introLines))->not->toContain('$');
});

it('emails every active member about new terms', function () {
    Notification::fake();
    [$one] = verifiedMember('Maria Alvarez');
    $this->artisan('members:notify-terms', ['effective' => '2027-01-15'])->assertSuccessful();
    Notification::assertSentTo($one, MemberNotice::class, fn ($n) => $n->security && str_contains($n->subject, 'Terms of Use'));
});

it('confirms an email address from the command line', function () {
    $u = User::factory()->unverified()->create(['email' => 'aoncareer@gmail.com']);
    $this->artisan('members:verify-email', ['email' => 'AonCareer@gmail.com'])->assertSuccessful();
    expect($u->fresh()->hasVerifiedEmail())->toBeTrue();
    $this->artisan('members:verify-email', ['email' => 'nobody@example.com'])->assertFailed();
});
