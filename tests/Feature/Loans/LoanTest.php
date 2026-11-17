<?php

use App\Models\AuditLog;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Services\Features;
use App\Services\Loans\LoanService;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    seedReference();
    config(['bank.default_daily_limit' => 50_000_000, 'bank.per_transaction_limit' => 50_000_000]);
});

/** A verified member with loans switched on and money in checking. */
function loanMember(int $balance = 500_000): array
{
    [$u, $checking, $savings] = verifiedMember('Maya Brooks');
    $u->forceFill(['kyc_status' => 'verified'])->save();
    Features::set($u, 'loans', true, makeStaff('Manager'));
    deposit($checking, $balance);

    return [$u->refresh(), $checking->refresh()];
}

function applyFor($u, $checking, int $amount = 1_200_000, int $term = 12, string $code = 'PERSONAL'): LoanApplication
{
    return app(LoanService::class)->apply($u, LoanProduct::where('code', $code)->first(), [
        'amount' => $amount, 'term_months' => $term, 'purpose' => 'Home repairs', 'annual_income' => 6_000_000, 'employment' => 'employed', 'deposit_account_id' => $checking->id,
    ]);
}

function makeLoan($u, $checking, int $amount = 1_200_000, int $term = 12, int $apr = 899): Loan
{
    $app = applyFor($u, $checking, $amount, $term);

    return app(LoanService::class)->approve($app, makeStaff('Manager'), $apr);
}

it('hides loans from a member without the flag', function () {
    [$u] = verifiedMember('Maya Brooks');
    $this->actingAs($u)->get('/borrow')->assertNotFound();
    Features::set($u, 'loans', true, makeStaff('Manager'));
    $this->actingAs($u->refresh())->get('/borrow')->assertOk()->assertSee('Personal loan');
});

it('lets a member apply and shows the application', function () {
    [$u, $checking] = loanMember();
    $this->actingAs($u)->post('/borrow/apply', [
        'product' => 'PERSONAL', 'amount' => '12000', 'term_months' => 24, 'purpose' => 'Home repairs', 'annual_income' => '60000', 'employment' => 'employed', 'deposit_account_id' => $checking->id,
    ])->assertRedirect();
    $app = LoanApplication::firstOrFail();
    expect($app->amount)->toBe(1_200_000)->and($app->status)->toBe('submitted');
    $this->actingAs($u)->get('/borrow/applications/'.$app->reference)->assertOk()->assertSee('Home repairs');
});

it('rejects an application outside the product limits, unverified members and a second open application', function () {
    [$u, $checking] = loanMember();
    expect(fn () => applyFor($u, $checking, 10_000_000))->toThrow(\Illuminate\Validation\ValidationException::class);
    expect(fn () => applyFor($u, $checking, 1_200_000, 18))->toThrow(\Illuminate\Validation\ValidationException::class);
    applyFor($u, $checking);
    expect(fn () => applyFor($u, $checking))->toThrow(\Illuminate\Validation\ValidationException::class);

    $u->forceFill(['kyc_status' => 'pending'])->save();
    expect(fn () => applyFor($u->refresh(), $checking))->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('requires vehicle details for an auto loan', function () {
    [$u, $checking] = loanMember();
    $this->actingAs($u)->post('/borrow/apply', ['product' => 'AUTO', 'amount' => '15000', 'term_months' => 48, 'purpose' => 'Car', 'annual_income' => '60000', 'employment' => 'employed', 'deposit_account_id' => $checking->id])->assertSessionHasErrors(['vehicle_make', 'vehicle_model']);
});

it('approves, deposits the money and builds a schedule that adds up to the principal', function () {
    [$u, $checking] = loanMember(0 + 1_000);
    $app = applyFor($u, $checking, 1_200_000, 12);
    $loan = app(LoanService::class)->approve($app, makeStaff('Manager'), 899);

    expect($checking->refresh()->balance->cents)->toBe(1_000 + 1_200_000);
    expect($loan->schedule()->count())->toBe(12)->and((int) $loan->schedule()->sum('principal'))->toBe(1_200_000)->and($loan->schedule()->reorder('installment', 'desc')->first()->balance_after)->toBe(0);
    expect($app->refresh()->status)->toBe('approved')->and($app->decisions()->count())->toBe(1)->and($loan->first_due_date->toDateString())->toBe(today()->addDays(30)->toDateString());
    expect(AuditLog::where('action', 'loan.approved')->count())->toBe(1);
});

it('keeps the approved rate inside the product range and the amount at or below the request', function () {
    [$u, $checking] = loanMember();
    $app = applyFor($u, $checking);
    $svc = app(LoanService::class);
    expect(fn () => $svc->approve($app, makeStaff('Manager'), 100))->toThrow(\Illuminate\Validation\ValidationException::class);
    expect(fn () => $svc->approve($app, makeStaff('Manager'), 899, 2_000_000))->toThrow(\Illuminate\Validation\ValidationException::class);
    expect($svc->approve($app, makeStaff('Manager'), 1099, 1_000_000)->principal)->toBe(1_000_000);
});

it('needs a reason to decline, and lets the member answer a question', function () {
    [$u, $checking] = loanMember();
    $app = applyFor($u, $checking);
    $staff = makeStaff('Manager');

    $this->actingAs($staff)->post('/admin/loans/applications/'.$app->reference, ['action' => 'reject'])->assertSessionHasErrors('reason_text');
    $this->actingAs($staff)->post('/admin/loans/applications/'.$app->reference, ['action' => 'info', 'info_request' => 'Please tell us your employer.'])->assertSessionHasNoErrors();
    expect($app->refresh()->status)->toBe('needs_info');
    $this->actingAs($u)->get('/borrow/applications/'.$app->reference)->assertSee('Please tell us your employer.');
    $this->actingAs($u)->post('/borrow/applications/'.$app->reference.'/reply', ['reply' => 'Acme Corp'])->assertSessionHasNoErrors();
    expect($app->refresh()->status)->toBe('in_review')->and($app->member_reply)->toBe('Acme Corp');

    $this->actingAs($staff)->post('/admin/loans/applications/'.$app->reference, ['action' => 'reject', 'reason_code' => 'over_limit', 'reason_text' => 'Debt is too high.']);
    expect($app->refresh()->status)->toBe('rejected');
    $this->actingAs($u)->get('/borrow/applications/'.$app->reference)->assertSee('Debt is too high.');
});

it('keeps loan decisions to staff with approval rights and loans private to their owner', function () {
    [$u, $checking] = loanMember();
    $app = applyFor($u, $checking);
    $this->actingAs(makeStaff('Teller'))->get('/admin/loans')->assertForbidden();
    $this->actingAs(makeStaff('Support Agent'))->post('/admin/loans/applications/'.$app->reference, ['action' => 'approve', 'apr' => '9'])->assertForbidden();
    $this->actingAs(makeStaff('Manager'))->get('/admin/loans')->assertOk()->assertSee('Maya Brooks');

    $loan = makeLoan(...loanMember());
    [$other] = verifiedMember('Noah Price');
    Features::set($other, 'loans', true, makeStaff('Manager'));
    $this->actingAs($other->refresh())->get('/borrow/loans/'.$loan->reference)->assertNotFound();
    $this->actingAs($other)->post('/borrow/loans/'.$loan->reference.'/pay', ['account_id' => 1, 'kind' => 'next'])->assertNotFound();
});

it('pays installments through the ledger until the loan is paid off', function () {
    [$u, $checking] = loanMember(0 + 5_000);
    $loan = makeLoan($u, $checking, 300_000, 12);
    deposit($checking->refresh(), 400_000);
    $svc = app(LoanService::class);
    $before = $checking->refresh()->balance->cents;

    $inst = $svc->payNext($loan, $checking);
    expect($checking->refresh()->balance->cents)->toBe($before - $inst->amountDue()->cents)->and($loan->refresh()->balance)->toBe($inst->balance_after)->and($inst->transaction->type->value)->toBe('loan_payment');
    for ($i = 2; $i <= 12; $i++) {
        $svc->payNext($loan->refresh(), $checking->refresh());
    }
    expect($loan->refresh()->status)->toBe('paid_off')->and($loan->balance)->toBe(0)->and($loan->next_due_date)->toBeNull();
    expect(fn () => $svc->payNext($loan, $checking))->toThrow(InvalidArgumentException::class);
});

it('declines a payment when the account lacks funds', function () {
    [$u, $checking] = loanMember(0 + 1_000);
    $loan = makeLoan($u, $checking, 300_000, 12);
    $empty = $u->accounts()->where('accounts.id', '!=', $checking->id)->first();
    expect(fn () => app(LoanService::class)->payNext($loan, $empty))->toThrow(\App\Services\Ledger\TransferException::class);
    expect($loan->schedule()->where('status', 'paid')->count())->toBe(0);
});

it('applies an extra payment to principal and rebuilds the remaining schedule', function () {
    [$u, $checking] = loanMember(0 + 1_000);
    $loan = makeLoan($u, $checking, 600_000, 12);
    $svc = app(LoanService::class);
    $svc->payNext($loan, $checking);
    $balance = $loan->refresh()->balance;

    $svc->payExtra($loan, $checking, Money::cents(100_000));
    $loan->refresh();
    expect($loan->balance)->toBe($balance - 100_000)->and($loan->schedule()->count())->toBe(12);
    $remaining = $loan->schedule()->where('status', 'due');
    expect($remaining->count())->toBe(11)->and((int) $remaining->sum('principal'))->toBe($loan->balance)->and($remaining->reorder('installment', 'desc')->first()->balance_after)->toBe(0);
    expect(fn () => $svc->payExtra($loan, $checking, Money::cents($loan->balance)))->toThrow(InvalidArgumentException::class);
});

it('pays a loan off with accrued interest included and closes it', function () {
    [$u, $checking] = loanMember(0 + 1_000);
    $loan = makeLoan($u, $checking, 600_000, 12);
    deposit($checking->refresh(), 50_000);
    $svc = app(LoanService::class);
    $this->travelTo(now()->addDays(15));
    $amount = $svc->payoffAmount($loan);
    expect($amount->cents)->toBeGreaterThan($loan->balance);

    $before = $checking->refresh()->balance->cents;
    $svc->payOff($loan, $checking);
    expect($loan->refresh()->status)->toBe('paid_off')->and($loan->balance)->toBe(0)->and($checking->refresh()->balance->cents)->toBe($before - $amount->cents)->and($loan->schedule()->where('status', 'due')->count())->toBe(0);
});

it('autopays on the due date and not before', function () {
    [$u, $checking] = loanMember(0 + 1_000);
    $loan = makeLoan($u, $checking, 300_000, 12);
    $this->actingAs($u)->post('/borrow/loans/'.$loan->reference.'/autopay', ['on' => 1, 'account_id' => $checking->id])->assertSessionHasNoErrors();
    expect($loan->refresh()->autopay_account_id)->toBe($checking->id);

    $svc = app(LoanService::class);
    $this->travelTo(now()->addDays(29));
    expect($svc->processDaily()['paid'])->toBe(0);
    $this->travelTo(now()->addDay());
    expect($svc->processDaily()['paid'])->toBe(1)->and($loan->schedule()->where('status', 'paid')->count())->toBe(1);
    expect($svc->processDaily()['paid'])->toBe(0);
});

it('records a failed autopay, then adds a late fee after the grace period and flags the loan delinquent', function () {
    [$u, $checking] = loanMember(0 + 1_000);
    $loan = makeLoan($u, $checking, 1_200_000, 12);
    $svc = app(LoanService::class);
    // empty the account so autopay has nothing to take
    $u->accounts()->get()->each(fn ($a) => $a->balance->cents > 0 ? app(\App\Services\Ledger\TransferService::class)->execute(new \App\Services\Ledger\TransferRequest(\App\Enums\TransactionType::Withdrawal, $a->balance, 'drain-'.$a->id, fromAccountId: $a->id)) : null);
    $svc->setAutopay($loan, $u, $checking->id);

    $this->travelTo(now()->addDays(30));
    expect($svc->processDaily()['failed'])->toBe(1);
    expect(AuditLog::where('action', 'loan.autopay_failed')->count())->toBe(1);

    $this->travelTo(now()->addDays(11));
    $r = $svc->processDaily();
    expect($r['fees'])->toBe(1)->and($loan->schedule()->first()->late_fee)->toBe(2_500);
    expect($svc->processDaily()['fees'])->toBe(0);

    $this->travelTo(now()->addDays(25));
    $svc->processDaily();
    expect($loan->refresh()->status)->toBe('delinquent');
    $this->actingAs($u)->get('/borrow/loans/'.$loan->reference)->assertSee('past due');
});

it('runs the daily command', function () {
    $this->artisan('loans:process')->assertSuccessful();
});

it('keeps a member from pulling money from an account that is not theirs', function () {
    [$u, $checking] = loanMember();
    $loan = makeLoan($u, $checking);
    [$other, $theirs] = verifiedMember('Noah Price');
    $this->actingAs($u)->post('/borrow/loans/'.$loan->reference.'/pay', ['account_id' => $theirs->id, 'kind' => 'next'])->assertSessionHas('toast');
    expect($loan->schedule()->where('status', 'paid')->count())->toBe(0);
});
