<?php

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\BillPayment;
use App\Models\Biller;
use App\Models\Goal;
use App\Models\Transaction;
use App\Services\BillPayService;
use App\Services\GoalService;
use App\Services\Ledger\TransferRequest;
use App\Services\Ledger\TransferService;
use App\Services\SpendingService;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    seedReference();
    config(['bank.default_daily_limit' => 50_000_000, 'bank.per_transaction_limit' => 50_000_000]);
    $this->travelTo(Carbon::parse('2026-11-18 10:00')); // a Wednesday
});

function spend($account, string $merchant, int $cents, TransactionType $type = TransactionType::CardPurchase)
{
    return app(TransferService::class)->execute(new TransferRequest($type, Money::cents($cents), 'sp-'.uniqid(), fromAccountId: $account->id, memberDescription: $merchant));
}

// ---------------------------------------------------------------- goals

it('creates a hidden goal sub-account and shows contributions in the parent ledger', function () {
    [$u, $checking, $savings] = verifiedMember('Maya Brooks');
    deposit($savings, 100_000);
    $goal = app(GoalService::class)->create($u, $savings, 'Vacation', 50_000, null);

    expect($goal->account->type)->toBe('goal')->and($goal->account->parent_account_id)->toBe($savings->id);
    app(GoalService::class)->contribute($goal, Money::cents(20_000));
    expect($savings->refresh()->balance->cents)->toBe(80_000)->and($goal->account->refresh()->balance->cents)->toBe(20_000);

    $tx = Transaction::where('to_account_id', $goal->account_id)->firstOrFail();
    expect($tx->from_account_id)->toBe($savings->id)->and($tx->entries()->count())->toBe(2);
    $this->actingAs($u)->get('/accounts/'.$savings->id)->assertSee('Saved to goal: Vacation');
    $this->actingAs($u)->get('/dashboard')->assertDontSee($goal->account->masked());
    $this->actingAs($u)->get('/transfers/new')->assertDontSee('Vacation');
    $this->actingAs($u)->get('/goals')->assertSee('Vacation')->assertSee('$200.00');
});

it('marks a goal reached, takes money back out, and returns the savings when closed', function () {
    [$u, , $savings] = verifiedMember('Maya Brooks');
    deposit($savings, 100_000);
    $svc = app(GoalService::class);
    $goal = $svc->create($u, $savings, 'Laptop', 30_000, null);
    $svc->contribute($goal, Money::cents(30_000));
    expect($goal->refresh()->status)->toBe('achieved')->and($goal->percent())->toBe(100);
    $svc->withdraw($goal, Money::cents(5_000));
    expect($goal->refresh()->status)->toBe('active');

    $svc->close($goal);
    expect($goal->refresh()->status)->toBe('closed')->and($goal->account->refresh()->balance->cents)->toBe(0)->and($savings->refresh()->balance->cents)->toBe(100_000);
    expect(fn () => $svc->contribute($goal, Money::cents(100)))->toThrow(InvalidArgumentException::class);
});

it('saves automatically on schedule and not before', function () {
    [$u, , $savings] = verifiedMember('Maya Brooks');
    deposit($savings, 100_000);
    $svc = app(GoalService::class);
    $goal = $svc->create($u, $savings, 'Rainy day', 100_000, null);
    $this->actingAs($u)->post('/goals/'.$goal->id.'/auto', ['auto_amount' => '25', 'auto_frequency' => 'weekly'])->assertSessionHasNoErrors();

    $this->travelTo(now()->addDays(6));
    expect($svc->runAuto())->toBe(0);
    $this->travelTo(now()->addDay());
    expect($svc->runAuto())->toBe(1)->and($goal->account->refresh()->balance->cents)->toBe(2_500);
    expect($svc->runAuto())->toBe(0);
    expect($goal->refresh()->auto_next_date->toDateString())->toBe(today()->addDays(7)->toDateString());
});

it('keeps goals private to their owner and refuses an account that is not theirs', function () {
    [$u, , $savings] = verifiedMember('Maya Brooks');
    [$other, , $theirs] = verifiedMember('Noah Price');
    $goal = app(GoalService::class)->create($u, $savings, 'Mine', 10_000, null);
    $this->actingAs($other)->post('/goals/'.$goal->id.'/move', ['amount' => '5', 'direction' => 'in'])->assertNotFound();
    $this->actingAs($other)->post('/goals', ['name' => 'Sneaky', 'target' => '100', 'parent_account_id' => $savings->id])->assertSessionHasErrors('parent_account_id');
});

// ----------------------------------------------------------------- bills

function biller($u): Biller
{
    return app(BillPayService::class)->addBiller($u, 'Cascade Electric', 'utilities', 'ACCT-4471');
}

it('sends a bill today through the ledger and estimates delivery in business days', function () {
    [$u, $checking] = verifiedMember('Maya Brooks');
    deposit($checking, 100_000);
    $p = app(BillPayService::class)->schedule($u, biller($u), $checking, Money::cents(12_000), today(), 'none');

    expect($p->status)->toBe('processing')->and($checking->refresh()->balance->cents)->toBe(88_000)->and($p->transaction->type->value)->toBe('bill_payment');
    expect($p->estimated_delivery->toDateString())->toBe('2026-11-23'); // Wed + 3 business days = Mon

    $this->travelTo(Carbon::parse('2026-11-24 09:00'));
    app(BillPayService::class)->processDaily();
    expect($p->refresh()->status)->toBe('delivered');
});

it('holds a future payment until its send date, then sends it and queues the next repeat', function () {
    [$u, $checking] = verifiedMember('Maya Brooks');
    deposit($checking, 100_000);
    $svc = app(BillPayService::class);
    $p = $svc->schedule($u, biller($u), $checking, Money::cents(5_000), today()->addDays(5), 'monthly');
    expect($p->status)->toBe('scheduled')->and($checking->refresh()->balance->cents)->toBe(100_000);

    $this->travelTo(now()->addDays(4));
    expect($svc->processDaily()[0])->toBe(0);
    $this->travelTo(now()->addDay());
    expect($svc->processDaily()[0])->toBe(1);
    expect($checking->refresh()->balance->cents)->toBe(95_000)->and($p->refresh()->status)->toBe('processing');
    $next = BillPayment::where('status', 'scheduled')->firstOrFail();
    expect($next->send_date->toDateString())->toBe(today()->addMonthNoOverflow()->toDateString());
});

it('sends a large payment to review and follows the decision', function () {
    [$u, $checking] = verifiedMember('Maya Brooks');
    deposit($checking, 1_000_000);
    $svc = app(BillPayService::class);
    $p = $svc->schedule($u, biller($u), $checking, Money::cents(400_000), today(), 'none');
    expect($p->status)->toBe('review')->and($p->transaction->status->value)->toBe('pending');

    app(TransferService::class)->approve($p->transaction, makeStaff('Manager'));
    $svc->refresh();
    expect($p->refresh()->status)->toBe('processing');
});

it('fails a payment with not enough money and records why', function () {
    [$u, $checking] = verifiedMember('Maya Brooks');
    deposit($checking, 1_000);
    $p = app(BillPayService::class)->schedule($u, biller($u), $checking, Money::cents(50_000), today(), 'none');
    expect($p->status)->toBe('failed')->and($p->failure)->toContain('not enough');
});

it('cancels a scheduled payment and keeps billers private', function () {
    [$u, $checking] = verifiedMember('Maya Brooks');
    [$other] = verifiedMember('Noah Price');
    deposit($checking, 100_000);
    $b = biller($u);
    $p = app(BillPayService::class)->schedule($u, $b, $checking, Money::cents(5_000), today()->addDays(3), 'none');

    $this->actingAs($other)->post('/bills/'.$p->id.'/cancel')->assertSessionHas('toast');
    expect($p->refresh()->status)->toBe('scheduled');
    $this->actingAs($other)->delete('/bills/billers/'.$b->id)->assertNotFound();
    $this->actingAs($u)->post('/bills/'.$p->id.'/cancel')->assertSessionHasNoErrors();
    expect($p->refresh()->status)->toBe('cancelled');
    $this->actingAs($u)->get('/bills')->assertOk()->assertSee('Cascade Electric');
});

it('runs the daily commands', function () {
    $this->artisan('bills:process')->assertSuccessful();
    $this->artisan('goals:run')->assertSuccessful();
    $this->artisan('insights:summarize')->assertSuccessful();
});

// ------------------------------------------------------------- overdraft

it('lets a member link and unlink overdraft protection, only to their own savings', function () {
    [$u, $checking, $savings] = verifiedMember('Maya Brooks');
    [, , $theirs] = verifiedMember('Noah Price');
    $this->actingAs($u)->put('/overdraft/'.$checking->id, ['source_id' => ''])->assertSessionHasNoErrors();
    expect($checking->refresh()->overdraft_account_id)->toBeNull();
    $this->actingAs($u)->put('/overdraft/'.$checking->id, ['source_id' => $savings->id]);
    expect($checking->refresh()->overdraft_account_id)->toBe($savings->id);
    $this->actingAs($u)->put('/overdraft/'.$checking->id, ['source_id' => $theirs->id])->assertSessionHas('toast');
    expect($checking->refresh()->overdraft_account_id)->toBe($savings->id);
    $this->actingAs($u)->get('/overdraft')->assertOk()->assertSee('Overdraft protection');
});

it('covers a shortfall from the linked savings and shows it as a protection transfer', function () {
    [$u, $checking, $savings] = verifiedMember('Maya Brooks');
    $checking->update(['overdraft_account_id' => $savings->id]);
    deposit($checking, 5_000);
    deposit($savings, 50_000);
    spend($checking, 'Harbor Coffee Co.', 8_000);
    expect($checking->refresh()->balance->cents)->toBe(0)->and($savings->refresh()->balance->cents)->toBe(47_000);
    $this->actingAs($u)->get('/overdraft')->assertSee('$30.00');
});

// -------------------------------------------------------------- insights

it('groups spending by category, ignores transfers, and lets the member recategorize', function () {
    [$u, $checking, $savings] = verifiedMember('Maya Brooks');
    deposit($checking, 200_000);
    spend($checking, 'Fieldstone Grocery', 6_000);
    $coffee = spend($checking, 'Harbor Coffee Co.', 1_000);
    spend($checking, 'Bill payment, Cascade Electric', 9_000, TransactionType::BillPayment);
    app(TransferService::class)->execute(new TransferRequest(TransactionType::Transfer, Money::cents(50_000), 'mv1', fromAccountId: $checking->id, toAccountId: $savings->id));

    $svc = app(SpendingService::class);
    $m = $svc->month($u, today());
    $cats = $svc->byCategory($m['spend']);
    expect($cats['groceries']['total'])->toBe(6_000)->and($cats['dining']['total'])->toBe(1_000)->and($cats['bills']['total'])->toBe(9_000)->and($m['income'])->toBe(200_000);
    expect(array_sum(array_column($cats, 'total')))->toBe(16_000);
    expect($svc->topMerchants($m['spend']))->toHaveKey('Fieldstone Grocery');

    $this->actingAs($u)->post('/insights/transactions/'.$coffee->reference.'/category', ['category' => 'entertainment'])->assertSessionHasNoErrors();
    expect($svc->byCategory($svc->month($u, today())['spend']))->toHaveKey('entertainment')->not->toHaveKey('dining');
    $this->actingAs($u)->get('/insights')->assertOk()->assertSee('Fieldstone Grocery');
});

it('warns near and over a budget, on the insights page and the dashboard', function () {
    [$u, $checking] = verifiedMember('Maya Brooks');
    deposit($checking, 200_000);
    $svc = app(SpendingService::class);
    $this->actingAs($u)->post('/insights/budgets', ['category' => 'dining', 'limit' => '100'])->assertSessionHasNoErrors();

    spend($checking, 'Harbor Coffee Co.', 5_000);
    expect($svc->budgetStatus($u)[0]['state'])->toBe('ok');
    $this->actingAs($u)->get('/dashboard')->assertDontSee('monthly budget');

    spend($checking, 'Harbor Coffee Co.', 3_500);
    expect($svc->budgetStatus($u)[0])->toMatchArray(['percent' => 85, 'state' => 'near']);
    $this->actingAs($u)->get('/insights')->assertSee('you have used 85% of your budget');
    $this->actingAs($u)->get('/dashboard')->assertSee('you have used 85% of your monthly budget');

    spend($checking, 'Harbor Coffee Co.', 2_000);
    expect($svc->budgetStatus($u)[0]['state'])->toBe('over');
    $this->actingAs($u)->post('/insights/budgets', ['category' => 'dining', 'limit' => ''])->assertSessionHasNoErrors();
    expect($svc->budgetStatus($u))->toBe([]);
});

it('stores a monthly summary and does not let a member recategorize someone else\'s transaction', function () {
    [$u, $checking] = verifiedMember('Maya Brooks');
    [$other] = verifiedMember('Noah Price');
    deposit($checking, 100_000);
    $tx = spend($checking, 'Redwood Fuel', 4_000);
    expect(app(SpendingService::class)->summarize(today()))->toBe(1);
    expect(\DB::table('spending_summaries')->where('user_id', $u->id)->where('category', 'fuel')->value('total'))->toBe(4_000);
    $this->actingAs($other)->post('/insights/transactions/'.$tx->reference.'/category', ['category' => 'other'])->assertNotFound();
});
