<?php

use App\Models\Account;
use App\Models\MemberProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use PragmaRX\Google2FA\Google2FA;

uses(RefreshDatabase::class);

beforeEach(fn () => seedReference());

it('opens a member with a savings and a checking account for free', function () {
    $this->post('/register', registrationData())->assertRedirect('/dashboard');

    $user = User::where('email', 'linda.nguyen@example.com')->firstOrFail();
    $accounts = $user->accounts()->with('product')->get();

    expect($user->member_number)->toMatch('/^\d{7}$/')
        ->and($user->role)->toBe('user')
        ->and($accounts)->toHaveCount(2)
        ->and($accounts->pluck('balance')->map->cents->sum())->toBe(0)
        ->and($accounts->firstWhere('product.kind', 'share_draft')->overdraft_account_id)->toBe($accounts->firstWhere('product.kind', 'share_savings')->id);

    $this->assertAuthenticatedAs($user);
});

it('stores the last four of the SSN encrypted', function () {
    $this->post('/register', registrationData());

    $raw = DB::table('member_profiles')->value('ssn_last4');
    expect($raw)->not->toBe('4821')->and($raw)->not->toContain('4821')
        ->and(MemberProfile::first()->ssn_last4)->toBe('4821');
});

it('keeps unverified members out of online banking until they confirm their email', function () {
    $this->post('/register', registrationData());
    $this->get('/dashboard')->assertRedirect(route('verification.notice'));

    $user = User::firstWhere('email', 'linda.nguyen@example.com');
    $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);
    $this->get($url)->assertRedirect();

    expect($user->refresh()->hasVerifiedEmail())->toBeTrue();
    $this->get('/dashboard')->assertOk();
});

it('checks membership eligibility', function () {
    $this->post('/register', registrationData(['eligibility_state' => 'NY']))->assertSessionHasErrors('eligibility_state');
    $this->post('/register', registrationData(['field_of_membership' => 'family_member', 'referred_by_member_number' => '1234567', 'eligibility_state' => null]))->assertSessionHasErrors('referred_by_member_number');
    expect(User::count())->toBe(0);

    $existing = makeUser();
    $this->post('/register', registrationData(['field_of_membership' => 'family_member', 'referred_by_member_number' => $existing->member_number, 'eligibility_state' => null]))->assertRedirect();
    expect(MemberProfile::first()->referred_by_member_number)->toBe($existing->member_number);
});

it('refuses applicants under 18, weak passwords and missing consent', function () {
    $this->post('/register', registrationData(['dob' => now()->subYears(17)->toDateString()]))->assertSessionHasErrors('dob');
    $this->post('/register', registrationData(['password' => 'short', 'password_confirmation' => 'short']))->assertSessionHasErrors('password');
    $this->post('/register', registrationData(['terms' => null]))->assertSessionHasErrors('terms');
    $this->post('/register', registrationData(['ssn_last4' => '12']))->assertSessionHasErrors('ssn_last4');
    expect(User::count())->toBe(0);
});

it('does not allow the same email twice', function () {
    $this->post('/register', registrationData());
    auth()->logout();
    $this->post('/register', registrationData())->assertSessionHasErrors('email');
});

it('logs a verified member in and shows the dashboard', function () {
    [$user] = verifiedMember();
    $user->forceFill(['password' => 'Correct-Horse-9'])->save();

    $this->post('/login', ['email' => $user->email, 'password' => 'Correct-Horse-9'])->assertRedirect('/dashboard');
    $this->get('/dashboard')->assertOk()->assertSee('Welcome back, Maria');
    expect($user->refresh()->last_login_at)->not->toBeNull();
});

it('rejects a wrong password with a generic message and locks after repeated tries', function () {
    [$user] = verifiedMember();

    for ($i = 0; $i < 5; $i++) {
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])->assertSessionHasErrors('email');
    }
    $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])->assertStatus(429);
});

it('sends signed-out visitors from online banking to log in', function () {
    foreach (['/dashboard', '/activity', '/transfers/new', '/profile', '/security-settings', '/accounts/open'] as $url) {
        $this->get($url)->assertRedirect('/login');
    }
});

it('lets a member reset a forgotten password', function () {
    [$user] = verifiedMember();
    $token = Illuminate\Support\Facades\Password::createToken($user);

    $this->post('/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'Brand-New-Pass-7', 'password_confirmation' => 'Brand-New-Pass-7'])->assertRedirect(route('login'));
    $this->post('/login', ['email' => $user->email, 'password' => 'Brand-New-Pass-7'])->assertRedirect('/dashboard');
});

it('turns on two-step verification and asks for a code at the next sign in', function () {
    [$user] = verifiedMember();
    $user->forceFill(['password' => 'Correct-Horse-9'])->save();

    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);
    $this->post('/user/two-factor-authentication')->assertRedirect();
    $this->get('/security-settings')->assertOk()->assertSee('Confirm and turn on');

    $secret = decrypt($user->refresh()->two_factor_secret);
    $code = (new Google2FA)->getCurrentOtp($secret);
    $this->post('/user/confirmed-two-factor-authentication', ['code' => $code])->assertRedirect();
    expect($user->refresh()->two_factor_confirmed_at)->not->toBeNull();
    $this->get('/security-settings')->assertSee('Recovery codes');

    auth()->logout();
    $this->flushSession();
    $this->post('/login', ['email' => $user->email, 'password' => 'Correct-Horse-9'])->assertRedirect(route('two-factor.login'));
    $this->assertGuest();

    $g = new Google2FA;
    $next = $g->oathTotp($secret, $g->getTimestamp() + 1);
    $this->post('/two-factor-challenge', ['code' => $next])->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($user);
});

it('accepts a recovery code once', function () {
    [$user] = verifiedMember();
    $user->forceFill(['password' => 'Correct-Horse-9'])->save();
    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);
    $this->post('/user/two-factor-authentication');
    $secret = decrypt($user->refresh()->two_factor_secret);
    $this->post('/user/confirmed-two-factor-authentication', ['code' => (new Google2FA)->getCurrentOtp($secret)]);
    $code = $user->refresh()->recoveryCodes()[0];

    auth()->logout();
    $this->flushSession();
    $this->post('/login', ['email' => $user->email, 'password' => 'Correct-Horse-9']);
    $this->post('/two-factor-challenge', ['recovery_code' => $code])->assertRedirect('/dashboard');
    expect($user->refresh()->recoveryCodes())->not->toContain($code);
});
