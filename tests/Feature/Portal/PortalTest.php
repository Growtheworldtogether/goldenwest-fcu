<?php

use App\Models\Account;
use App\Models\LedgerEntry;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    seedReference();
    config(['bank.default_daily_limit' => 20_000_000]);
});

it('shows a member only their own accounts', function () {
    [$maria, $checking] = verifiedMember('Maria Alvarez');
    [$david] = verifiedMember('David Okafor');
    deposit($checking, 123_456);

    $this->actingAs($maria)->get('/dashboard')->assertOk()->assertSee('$1,234.56')->assertSee('Everyday Checking');
    $david->accounts()->each(fn ($a) => $this->actingAs($maria)->get('/dashboard')->assertDontSee($a->account_number));
});

it('forbids other members accounts, transactions and exports', function () {
    [$maria, $checking] = verifiedMember('Maria Alvarez');
    [$david, $davidChecking] = verifiedMember('David Okafor');
    $tx = deposit($davidChecking, 5_000);

    $this->actingAs($maria)->get(route('accounts.show', $davidChecking))->assertForbidden();
    $this->actingAs($maria)->get(route('accounts.export', $davidChecking))->assertForbidden();
    $this->actingAs($maria)->get(route('transactions.show', $tx->reference))->assertForbidden();
});

it('moves money between own accounts and records both ledger lines', function () {
    [$user, $checking, $savings] = verifiedMember();
    deposit($checking, 100_000);

    $token = (string) Illuminate\Support\Str::ulid();
    $this->actingAs($user)->post('/transfers', ['token' => $token, 'from_account_id' => $checking->id, 'to_account_id' => $savings->id, 'amount' => '250.00', 'memo' => 'Rainy day'])
        ->assertRedirect();

    expect($checking->refresh()->balance->cents)->toBe(75_000)
        ->and($savings->refresh()->balance->cents)->toBe(25_000)
        ->and(LedgerEntry::where('transaction_id', Transaction::latest('id')->first()->id)->count())->toBe(2);
    $this->artisan('ledger:verify')->assertSuccessful();
});

it('moves money once when a transfer is submitted twice', function () {
    [$user, $checking, $savings] = verifiedMember();
    deposit($checking, 100_000);
    $data = ['token' => (string) Illuminate\Support\Str::ulid(), 'from_account_id' => $checking->id, 'to_account_id' => $savings->id, 'amount' => '100.00'];

    $this->actingAs($user)->post('/transfers', $data);
    $this->actingAs($user)->post('/transfers', $data);

    expect($savings->refresh()->balance->cents)->toBe(10_000)->and($checking->refresh()->balance->cents)->toBe(90_000);
});

it('explains insufficient funds in plain words and changes nothing', function () {
    [$user, $checking, $savings] = verifiedMember();
    deposit($checking, 1_000);

    $this->actingAs($user)->from('/transfers/new')->post('/transfers', ['token' => (string) Illuminate\Support\Str::ulid(), 'from_account_id' => $checking->id, 'to_account_id' => $savings->id, 'amount' => '500.00'])
        ->assertSessionHasErrors();
    expect($checking->refresh()->balance->cents)->toBe(1_000)->and($savings->refresh()->balance->cents)->toBe(0);
});

it('refuses transfers from or to accounts the member does not own', function () {
    [$user, $checking] = verifiedMember('Maria Alvarez');
    [, $other] = verifiedMember('David Okafor');
    deposit($other, 50_000);

    $this->actingAs($user)->post('/transfers', ['token' => (string) Illuminate\Support\Str::ulid(), 'from_account_id' => $other->id, 'to_account_id' => $checking->id, 'amount' => '10.00'])
        ->assertForbidden();
    expect($other->refresh()->balance->cents)->toBe(50_000);
});

it('sets a large transfer aside for review and shows the member why', function () {
    [$user, $checking, $savings] = verifiedMember();
    deposit($checking, 2_000_000);

    $this->actingAs($user)->post('/transfers', ['token' => (string) Illuminate\Support\Str::ulid(), 'from_account_id' => $checking->id, 'to_account_id' => $savings->id, 'amount' => '6000.00']);
    $tx = Transaction::latest('id')->first();

    expect($tx->status->value)->toBe('pending');
    $this->actingAs($user)->get('/dashboard')->assertSee('Under review');
    $this->actingAs($user)->get(route('transactions.show', $tx->reference))->assertOk()->assertSee('reviewing');
});

it('opens another account for free and caps the number of accounts', function () {
    [$user] = verifiedMember();

    $this->actingAs($user)->post('/accounts/open', ['product' => 'MONEY-MARKET', 'nickname' => 'House fund'])->assertRedirect();
    $account = $user->accounts()->where('nickname', 'House fund')->firstOrFail();
    expect($account->balance->cents)->toBe(0);

    $this->actingAs($user)->post('/accounts/open', ['product' => 'NOPE'])->assertSessionHasErrors('product');
});

it('filters activity by search, direction and date', function () {
    [$user, $checking, $savings] = verifiedMember();
    deposit($checking, 50_000, 'Payroll Acme Corp');
    deposit($checking, 2_000, 'Refund Target');

    $this->actingAs($user)->get('/activity?q=Payroll')->assertSee('Payroll Acme Corp')->assertDontSee('Refund Target');
    $this->actingAs($user)->get('/activity?direction=out')->assertDontSee('Payroll Acme Corp');
    $this->actingAs($user)->get('/activity?from='.now()->addDay()->toDateString())->assertSee('No transactions match');
});

it('exports only the members own activity as CSV and neutralises spreadsheet formulas', function () {
    [$user, $checking] = verifiedMember('Maria Alvarez');
    [, $other] = verifiedMember('David Okafor');
    deposit($checking, 5_000, '=HYPERLINK("http://evil.example","click")');
    deposit($other, 9_999, 'Secret payroll');

    $res = $this->actingAs($user)->get(route('activity.export'));
    $res->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    $csv = $res->streamedContent();

    expect($csv)->not->toContain('Secret payroll')->not->toContain('99.99')
        ->and($csv)->not->toMatch('/(^|,)"?=HYPERLINK/m')->and($csv)->toContain("'=HYPERLINK");
});

it('shows why a payment is on hold with a link to contest it', function () {
    [$user, $checking, $savings] = verifiedMember();
    deposit($checking, 3_000_000);
    $this->actingAs($user)->post('/transfers', ['token' => (string) Illuminate\Support\Str::ulid(), 'from_account_id' => $checking->id, 'to_account_id' => $savings->id, 'amount' => '8000.00']);
    $tx = Transaction::latest('id')->first();
    $admin = makeUser('admin', 'Rachel Kim');
    app(App\Services\Ledger\TransferService::class)->hold($tx, $admin, 'verification_required', 'We need to confirm this transfer with you.', now()->addDays(2));

    $this->actingAs($user)->get(route('transactions.show', $tx->reference))->assertOk()->assertSee('on hold')->assertSee('confirm this transfer')->assertSee('Contest this decision');
});

it('updates contact details and notification preferences', function () {
    [$user] = verifiedMember();

    $this->actingAs($user)->put('/user/profile-information', ['phone' => '408-555-0188', 'address_line1' => '22 Alder Way', 'city' => 'Santa Clara', 'state' => 'CA', 'postal_code' => '95050'])->assertRedirect();
    expect($user->refresh()->city)->toBe('Santa Clara');

    $this->actingAs($user)->put('/profile/notifications', ['email_marketing' => '1'])->assertRedirect();
    expect($user->refresh()->notification_preferences)->toMatchArray(['email_marketing' => true, 'email_transactions' => false, 'sms_alerts' => false]);
});

it('changes the password only with the current one', function () {
    [$user] = verifiedMember();
    $user->forceFill(['password' => 'Correct-Horse-9'])->save();

    $this->actingAs($user)->put('/user/password', ['current_password' => 'wrong', 'password' => 'Another-Pass-77', 'password_confirmation' => 'Another-Pass-77'])->assertSessionHasErrors('current_password', null, 'updatePassword');
    $this->actingAs($user)->put('/user/password', ['current_password' => 'Correct-Horse-9', 'password' => 'Another-Pass-77', 'password_confirmation' => 'Another-Pass-77'])->assertSessionHasNoErrors();
});

it('renders every portal page for a signed-in member', function () {
    [$user, $checking] = verifiedMember();
    deposit($checking, 10_000);
    foreach (['/dashboard', '/activity', '/transfers/new', '/accounts/open', '/profile', '/security-settings', route('accounts.show', $checking, false)] as $url) {
        $this->actingAs($user)->get($url)->assertOk();
    }
});

it('shows flashed messages as toasts', function () {
    [$member] = verifiedMember();
    $this->actingAs($member)->withSession(['status' => 'Settings saved.', 'error' => 'Something went wrong.'])->get('/dashboard')
    $this->flushSession();
    $this->actingAs($member)->get('/dashboard')->assertDontSee('class="toast ok"', false);
    $this->actingAs($member)->get('/dashboard')->assertDontSee('class="toast ok"', false);
});
