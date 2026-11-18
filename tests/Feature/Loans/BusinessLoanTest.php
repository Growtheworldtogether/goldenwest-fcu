<?php

use App\Models\BusinessProfile;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Services\AccountOpening;
use App\Services\Features;
use App\Services\Loans\LoanService;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    seedReference();
    config(['bank.default_daily_limit' => 50_000_000, 'bank.per_transaction_limit' => 50_000_000]);
});

/** A verified owner with a business, loans switched on, and business checking. @return array{0: \App\Models\User, 1: \App\Models\Account, 2: \App\Models\Account} */
function businessOwner(): array
{
    [$u, $checking] = verifiedMember('Ben Ortiz');
    $u->forceFill(['kyc_status' => 'verified'])->save();
    BusinessProfile::create(['user_id' => $u->id, 'legal_name' => 'Ortiz Roofing LLC', 'entity_type' => 'llc', 'ein' => '123456789', 'industry' => 'Roofing', 'started_year' => 2018, 'address_line1' => '90 Main St', 'city' => 'Oakland', 'state' => 'CA', 'postal_code' => '94607']);
    [$bizChecking] = app(AccountOpening::class)->openBusiness($u, 'checking');
    Features::set($u, 'loans', true, makeStaff('Manager'));

    return [$u->refresh(), $bizChecking->refresh(), $checking];
}

function applyBusiness($u, $account, string $code, int $amount, int $term = 36, array $extra = [])
{
    return app(LoanService::class)->apply($u, LoanProduct::where('code', $code)->first(), array_merge([
        'amount' => $amount, 'term_months' => $term, 'purpose' => 'Growth', 'annual_income' => 40_000_000, 'employment' => 'business', 'deposit_account_id' => $account->id,
    ], $extra));
}

it('offers business loans only to members who have a business', function () {
    [$u] = businessOwner();
    $this->actingAs($u)->get('/borrow')->assertOk()->assertSee('Business term loan')->assertSee('Equipment loan')->assertSee('Business line of credit');
    [$plain] = verifiedMember('Maya Brooks');
    Features::set($plain, 'loans', true, makeStaff('Manager'));
    $this->actingAs($plain->refresh())->get('/borrow')->assertOk()->assertDontSee('Business term loan')->assertDontSee('Equipment loan');
    $this->actingAs($plain)->post('/borrow/apply', ['product' => 'BUS-TERM', 'amount' => '20000', 'term_months' => 36, 'purpose' => 'x', 'annual_income' => '100000', 'deposit_account_id' => 1])->assertNotFound();
});

it('pays a business term loan into a business account and builds the schedule', function () {
    [$u, $biz] = businessOwner();
    $app = applyBusiness($u, $biz, 'BUS-TERM', 2_500_000, 36);
    $loan = app(LoanService::class)->approve($app, makeStaff('Manager'), 899);
    expect($biz->fresh()->balance->cents)->toBe(2_500_000)->and($loan->schedule()->count())->toBe(36)->and($loan->revolving)->toBeFalse();
});

it('will not pay a business loan into a personal account, or a personal loan into a business one', function () {
    [$u, $biz, $personal] = businessOwner();
    expect(fn () => applyBusiness($u, $personal, 'BUS-TERM', 2_500_000))->toThrow(ValidationException::class);
    expect(fn () => app(LoanService::class)->apply($u, LoanProduct::where('code', 'PERSONAL')->first(), ['amount' => 500_000, 'term_months' => 12, 'purpose' => 'x', 'annual_income' => 6_000_000, 'employment' => 'employed', 'deposit_account_id' => $biz->id]))->toThrow(ValidationException::class);
});

it('needs the equipment and its cost for an equipment loan', function () {
    [$u, $biz] = businessOwner();
    expect(fn () => applyBusiness($u, $biz, 'BUS-EQUIP', 3_000_000, 48))->toThrow(ValidationException::class);
    $app = applyBusiness($u, $biz, 'BUS-EQUIP', 3_000_000, 48, ['collateral' => 'Kubota mini excavator', 'collateral_value' => 3_500_000]);
    expect($app->collateral)->toBe('Kubota mini excavator')->and($app->collateral_value)->toBe(3_500_000);
});

it('applies through the form and shows staff the business', function () {
    [$u, $biz] = businessOwner();
    $this->actingAs($u)->post('/borrow/apply', ['product' => 'BUS-TERM', 'amount' => '25000', 'term_months' => 36, 'purpose' => 'New truck', 'annual_income' => '400000', 'deposit_account_id' => $biz->id])->assertRedirect();
    $app = LoanApplication::firstOrFail();
    expect($app->employment)->toBe('business');
    auth()->logout();
    $this->actingAs(makeStaff('Manager'))->get('/admin/loans/applications/'.$app->reference)->assertOk()->assertSee('Ortiz Roofing LLC')->assertSee('••-•••6789', false)->assertSee('Annual revenue');
});

it('opens a line of credit with nothing owed and lets the owner draw on it', function () {
    [$u, $biz] = businessOwner();
    $app = applyBusiness($u, $biz, 'BUS-LOC', 5_000_000, 12);
    $loan = app(LoanService::class)->approve($app, makeStaff('Manager'), 1000);
    expect($loan->revolving)->toBeTrue()->and($loan->balance)->toBe(0)->and($biz->fresh()->balance->cents)->toBe(0)->and($loan->schedule()->count())->toBe(0);

    $this->actingAs($u)->post('/borrow/loans/'.$loan->reference.'/draw', ['account_id' => $biz->id, 'amount' => '12000'])->assertSessionHas('toast.type', 'ok');
    expect($biz->fresh()->balance->cents)->toBe(1_200_000)->and($loan->fresh()->balance)->toBe(1_200_000)->and($loan->fresh()->availableCredit()->cents)->toBe(3_800_000);
});

it('holds draws to the limit, to business accounts and to the owner', function () {
    [$u, $biz, $personal] = businessOwner();
    $loan = app(LoanService::class)->approve(applyBusiness($u, $biz, 'BUS-LOC', 5_000_000, 12), makeStaff('Manager'), 1000);
    $svc = app(LoanService::class);
    expect(fn () => $svc->draw($loan, $u, Money::cents(5_000_001), $biz->id))->toThrow(InvalidArgumentException::class);
    expect(fn () => $svc->draw($loan, $u, Money::cents(100_000), $personal->id))->toThrow(InvalidArgumentException::class);
    expect(fn () => $svc->draw($loan, $u, Money::cents(0), $biz->id))->toThrow(InvalidArgumentException::class);
    [$other] = verifiedMember('Someone Else');
    $this->actingAs($other)->post('/borrow/loans/'.$loan->reference.'/draw', ['account_id' => $biz->id, 'amount' => '10'])->assertNotFound();
    expect($loan->fresh()->balance)->toBe(0);
});

it('bills a line of credit once a month and takes the payment down', function () {
    [$u, $biz] = businessOwner();
    $svc = app(LoanService::class);
    $loan = $svc->approve(applyBusiness($u, $biz, 'BUS-LOC', 5_000_000, 12), makeStaff('Manager'), 1200);
    $svc->draw($loan, $u, Money::cents(1_000_000), $biz->id);

    expect($svc->billLines())->toBe(0);
    $this->travel(40)->days();
    expect($svc->billLines())->toBe(1);
    $bill = $loan->fresh()->schedule()->first();
    expect($bill->interest)->toBe(10_000)->and($bill->principal)->toBe(10_000);
    expect($svc->billLines())->toBe(0);

    $svc->payNext($loan->fresh(), $biz->fresh());
    $loan->refresh();
    expect($loan->balance)->toBe(990_000)->and($loan->status)->toBe('active')->and($loan->schedule()->where('status', 'due')->count())->toBe(0);
});

it('keeps the line open after it is paid off and lets the owner draw again', function () {
    [$u, $biz] = businessOwner();
    $svc = app(LoanService::class);
    $loan = $svc->approve(applyBusiness($u, $biz, 'BUS-LOC', 5_000_000, 12), makeStaff('Manager'), 1000);
    $svc->draw($loan, $u, Money::cents(500_000), $biz->id);
    $svc->payOff($loan->fresh(), $biz->fresh());
    $loan->refresh();
    expect($loan->balance)->toBe(0)->and($loan->status)->toBe('active');
    $svc->draw($loan, $u, Money::cents(100_000), $biz->id);
    expect($loan->fresh()->balance)->toBe(100_000);
});

it('shows the line of credit page with its limit and the draw form', function () {
    [$u, $biz] = businessOwner();
    $loan = app(LoanService::class)->approve(applyBusiness($u, $biz, 'BUS-LOC', 5_000_000, 12), makeStaff('Manager'), 1000);
    $this->actingAs($u)->get('/borrow/loans/'.$loan->reference)->assertOk()->assertSee('Draw funds')->assertSee('Available to draw')->assertSee('$50,000.00 limit');
    $this->get('/borrow')->assertOk()->assertSee('of a $50,000.00 limit');
});
