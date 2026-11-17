<?php

use App\Models\AuditLog;
use App\Models\Deposit;
use App\Models\Payee;
use App\Models\Ticket;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    seedReference();
    Storage::fake('local');
    config(['bank.default_daily_limit' => 50_000_000, 'bank.per_transaction_limit' => 50_000_000]);
});

function verified(string $name = 'Maria Alvarez'): array
{
    [$member, $checking, $savings] = verifiedMember($name);
    $member->forceFill(['kyc_status' => 'verified'])->save();

    return [$member->refresh(), $checking, $savings];
}

function checkPost(App\Models\User $member, App\Models\Account $to, string $amount = '250.00', array $extra = [])
{
    return test()->actingAs($member)->post('/deposits/check', array_merge([
        'token' => (string) Str::ulid(), 'account_id' => $to->id, 'amount' => $amount, 'check_number' => '1042',
        'front' => UploadedFile::fake()->image('front.jpg', 640, 300), 'back' => UploadedFile::fake()->image('back.jpg', 640, 300),
    ], $extra));
}

/** A valid ABA routing number (the checksum passes). */
const GOOD_ROUTING = '021000021';

function addPayee(App\Models\User $member, string $acct = '123456789012'): Payee
{
    test()->actingAs($member)->post('/payees', ['name' => 'Maria Alvarez', 'bank_name' => 'Harbor Bank', 'routing_number' => GOOD_ROUTING, 'account_number' => $acct, 'account_number_confirmation' => $acct, 'account_type' => 'checking'])->assertSessionHasNoErrors();

    return Payee::latest('id')->first();
}

it('keeps a check deposit pending and stores the images privately', function () {
    [$member, $checking] = verified();

    checkPost($member, $checking)->assertRedirect('/deposits');

    $tx = Transaction::latest('id')->first();
    $deposit = Deposit::first();
    expect($tx->status->value)->toBe('pending')->and($checking->refresh()->balance->cents)->toBe(0)->and($deposit->method)->toBe('check');
    Storage::disk('local')->assertExists($deposit->front_path);
    expect($deposit->front_path)->toStartWith('private/checks/')->and($deposit->front_path)->not->toContain('1042');
    $this->actingAs($member)->get('/deposits')->assertSee('In review')->assertSee('$250.00');
});

it('credits a check deposit only after staff approve it', function () {
    [$member, $checking] = verified();
    checkPost($member, $checking, '400.00');
    $tx = Transaction::latest('id')->first();

    $this->actingAs(makeStaff('Manager'))->post("/admin/queue/{$tx->reference}/approve")->assertRedirect();

    expect($tx->refresh()->status->value)->toBe('completed')->and($checking->refresh()->balance->cents)->toBe(40_000);
    $this->artisan('ledger:verify')->assertSuccessful();
});

it('shows the member why a check was rejected and lets them contest, opening one linked ticket', function () {
    [$member, $checking] = verified();
    checkPost($member, $checking, '400.00');
    $tx = Transaction::latest('id')->first();
    $this->actingAs(makeStaff('Manager'))->post("/admin/queue/{$tx->reference}/reject", ['reason_code' => 'unclear_image', 'reason_text' => 'The back of the check is cut off.'])->assertRedirect();

    $this->actingAs($member)->get("/transactions/{$tx->reference}")->assertOk()->assertSee('The back of the check is cut off.')->assertSee('Contest this decision');

    $this->actingAs($member)->post("/transactions/{$tx->reference}/contest", ['message' => 'The check was fully visible.'])->assertRedirect();
    $ticket = Ticket::first();
    expect($ticket->transaction_id)->toBe($tx->id)->and($ticket->user_id)->toBe($member->id)->and($ticket->category)->toBe('transaction');
    $this->actingAs($member)->get("/tickets/{$ticket->reference}")->assertOk()->assertSee('The check was fully visible.')->assertSee('cut off');

    $this->actingAs($member)->post("/transactions/{$tx->reference}/contest", [])->assertRedirect("/tickets/{$ticket->reference}");
    expect(Ticket::count())->toBe(1);

    $this->actingAs(makeStaff('Manager'))->get("/admin/queue/{$tx->reference}")->assertSee('Member contested this decision')->assertSee('The check was fully visible.');
});

it('does not let another member contest or read someone else\'s transaction or ticket', function () {
    [$member, $checking] = verified();
    checkPost($member, $checking);
    $tx = Transaction::latest('id')->first();
    $this->actingAs(makeStaff('Manager'))->post("/admin/queue/{$tx->reference}/reject", ['reason_code' => 'unclear_image', 'reason_text' => 'Blurry.']);
    $this->actingAs($member)->post("/transactions/{$tx->reference}/contest", []);
    $ticket = Ticket::first();
    [$other] = verified('Sam Rivera');

    $this->actingAs($other)->post("/transactions/{$tx->reference}/contest", [])->assertForbidden();
    $this->actingAs($other)->get("/tickets/{$ticket->reference}")->assertNotFound();
    expect(Ticket::count())->toBe(1);
});

it('cannot contest a transaction that is neither declined nor held', function () {
    [$member, $checking] = verified();
    checkPost($member, $checking);
    $tx = Transaction::latest('id')->first();

    $this->actingAs($member)->post("/transactions/{$tx->reference}/contest", [])->assertSessionHas('error');
    expect(Ticket::count())->toBe(0);
});

it('asks unverified members to verify before depositing a check', function () {
    [$member, $checking] = verifiedMember();

    checkPost($member, $checking)->assertSessionHas('error');
    expect(Deposit::count())->toBe(0)->and(Transaction::count())->toBe(0);
    $this->actingAs($member)->get('/deposits/check')->assertSee('Verify my identity');
});

it('refuses images that are not real PNG, JPG or WebP photos and checks above the limit', function () {
    [$member, $checking] = verified();

    checkPost($member, $checking, '100.00', ['front' => UploadedFile::fake()->createWithContent('front.png', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')])->assertSessionHasErrors('front');
    checkPost($member, $checking, '100.00', ['back' => UploadedFile::fake()->createWithContent('back.jpg', 'not an image at all')])->assertSessionHasErrors('back');
    checkPost($member, $checking, '100.00', ['front' => UploadedFile::fake()->create('x.pdf', 20, 'application/pdf')])->assertSessionHasErrors('front');
    checkPost($member, $checking, '10000.01')->assertSessionHas('error');
    expect(Deposit::count())->toBe(0);
});

it('moves money once when a check deposit form is sent twice', function () {
    [$member, $checking] = verified();
    $token = (string) Str::ulid();

    checkPost($member, $checking, '75.00', ['token' => $token]);
    checkPost($member, $checking, '75.00', ['token' => $token]);

    expect(Deposit::count())->toBe(1)->and(Transaction::count())->toBe(1);
});

it('serves check images only to the owner and staff, and audits staff views', function () {
    [$member, $checking] = verified();
    checkPost($member, $checking);
    $deposit = Deposit::first();
    [$other] = verified('Sam Rivera');
    $teller = makeStaff('Teller');
    $support = makeStaff('Support Agent');

    $this->actingAs($member)->get("/files/check/{$deposit->id}/front")->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->actingAs($other)->get("/files/check/{$deposit->id}/front")->assertForbidden();
    $this->actingAs($support)->get("/files/check/{$deposit->id}/front")->assertForbidden();
    auth()->logout();
    $this->get("/files/check/{$deposit->id}/front")->assertRedirect('/login');

    $this->actingAs($teller)->get("/files/check/{$deposit->id}/back")->assertOk();
    expect(AuditLog::where('action', 'deposit.image_viewed')->where('actor_id', $teller->id)->count())->toBe(1);
    $this->actingAs($member)->get("/files/check/{$deposit->id}/side")->assertNotFound();
});

it('checks the routing number and keeps the account number encrypted', function () {
    [$member] = verified();

    $this->actingAs($member)->post('/payees', ['name' => 'Maria', 'routing_number' => '123456789', 'account_number' => '5551234', 'account_number_confirmation' => '5551234', 'account_type' => 'checking'])->assertSessionHasErrors('routing_number');
    $this->actingAs($member)->post('/payees', ['name' => 'Maria', 'routing_number' => GOOD_ROUTING, 'account_number' => '5551234', 'account_number_confirmation' => '9999999', 'account_type' => 'checking'])->assertSessionHasErrors('account_number');

    $payee = addPayee($member, '5551234567');
    expect(DB::table('payees')->where('id', $payee->id)->value('account_number'))->not->toContain('5551234567')->and($payee->account_number)->toBe('5551234567')->and($payee->last4)->toBe('4567');
    $this->actingAs($member)->get('/payees')->assertSee('ending 4567')->assertDontSee('5551234567');
    $this->actingAs($member)->post('/payees', ['name' => 'Maria', 'routing_number' => GOOD_ROUTING, 'account_number' => '5551234567', 'account_number_confirmation' => '5551234567', 'account_type' => 'checking'])->assertSessionHasErrors('account_number');
});

it('blocks a new external account during the cooling-off period, then allows it', function () {
    [$member, $checking] = verified();
    $payee = addPayee($member);

    $this->actingAs($member)->post('/deposits/from-another-bank', ['token' => (string) Str::ulid(), 'account_id' => $checking->id, 'payee_id' => $payee->id, 'amount' => '100.00'])->assertSessionHas('error');
    expect(Transaction::count())->toBe(0);

    $this->travel(25)->hours();
    $this->actingAs($member)->post('/deposits/from-another-bank', ['token' => (string) Str::ulid(), 'account_id' => $checking->id, 'payee_id' => $payee->id, 'amount' => '100.00'])->assertRedirect('/deposits');

    $tx = Transaction::latest('id')->first();
    expect($tx->status->value)->toBe('pending')->and($tx->payee_id)->toBe($payee->id)->and($tx->effective_date->isWeekday())->toBeTrue()->and($checking->refresh()->balance->cents)->toBe(0);
    expect(Deposit::first()->method)->toBe('ach');
});

it('cannot use someone else\'s external account', function () {
    [$member, $checking] = verified();
    [$other] = verified('Sam Rivera');
    $theirs = addPayee($other);
    $this->travel(25)->hours();

    $this->actingAs($member)->post('/deposits/from-another-bank', ['token' => (string) Str::ulid(), 'account_id' => $checking->id, 'payee_id' => $theirs->id, 'amount' => '100.00'])->assertNotFound();
    $this->actingAs($member)->delete("/payees/{$theirs->id}")->assertNotFound();
    expect(Transaction::count())->toBe(0);
});

it('lets a manager end the waiting period and records it, but not a teller', function () {
    [$member, $checking] = verified();
    $payee = addPayee($member);

    $this->actingAs(makeStaff('Teller'))->post("/admin/members/{$member->id}/payees/{$payee->id}/waive")->assertForbidden();
    $manager = makeStaff('Manager');
    $this->actingAs($manager)->post("/admin/members/{$member->id}/payees/{$payee->id}/waive")->assertRedirect();

    expect($payee->refresh()->isActive())->toBeTrue()->and($payee->waived_by)->toBe($manager->id);
    expect(AuditLog::where('action', 'payee.cooling_off_waived')->where('actor_id', $manager->id)->exists())->toBeTrue();
});

it('sends an ACH payment out of an account and holds large ones for review', function () {
    [$member, $checking] = verified();
    deposit($checking, 2_000_000);
    $payee = addPayee($member);
    $this->travel(25)->hours();

    $this->actingAs($member)->post('/transfers/external', ['token' => (string) Str::ulid(), 'account_id' => $checking->id, 'payee_id' => $payee->id, 'amount' => '120.00'])->assertRedirect();
    $small = Transaction::latest('id')->first();
    expect($small->type->value)->toBe('ach_out')->and($small->status->value)->toBe('completed')->and($checking->refresh()->balance->cents)->toBe(1_988_000);

    $this->actingAs($member)->post('/transfers/external', ['token' => (string) Str::ulid(), 'account_id' => $checking->id, 'payee_id' => $payee->id, 'amount' => '6000.00'])->assertRedirect();
    $big = Transaction::latest('id')->first();
    expect($big->status->value)->toBe('pending')->and($checking->refresh()->available_balance->cents)->toBe(1_388_000);
    $this->artisan('ledger:verify')->assertSuccessful();
});

it('lets a teller record cash that posts at once, with an audit entry', function () {
    [$member, $checking] = verified();
    $teller = makeStaff('Teller');

    $this->actingAs($teller)->post("/admin/members/{$member->id}/cash-deposits", ['token' => (string) Str::ulid(), 'account_id' => $checking->id, 'amount' => '300.00', 'note' => 'Counter'])->assertRedirect();

    expect($checking->refresh()->balance->cents)->toBe(30_000)->and(Deposit::first()->recorded_by)->toBe($teller->id);
    expect(AuditLog::where('action', 'deposit.cash_recorded')->where('actor_id', $teller->id)->exists())->toBeTrue();
    $this->actingAs($member)->get("/activity")->assertSee('Cash deposit');
});

it('makes a large cash deposit wait for a second person, never the teller who recorded it', function () {
    [$member, $checking] = verified();
    $teller = makeStaff('Teller');

    $this->actingAs($teller)->post("/admin/members/{$member->id}/cash-deposits", ['token' => (string) Str::ulid(), 'account_id' => $checking->id, 'amount' => '12000.00'])->assertRedirect();
    $tx = Transaction::latest('id')->first();
    expect($tx->status->value)->toBe('pending')->and($checking->refresh()->balance->cents)->toBe(0);

    $this->actingAs($teller)->post("/admin/queue/{$tx->reference}/approve")->assertForbidden();
    $this->actingAs(makeStaff('Manager', true, 'Pat Manager'))->post("/admin/queue/{$tx->reference}/approve")->assertRedirect();
    expect($checking->refresh()->balance->cents)->toBe(1_200_000);
});

it('does not let staff without the deposit permission record cash', function () {
    [$member, $checking] = verified();

    $this->actingAs(makeStaff('Support Agent'))->post("/admin/members/{$member->id}/cash-deposits", ['token' => (string) Str::ulid(), 'account_id' => $checking->id, 'amount' => '10.00'])->assertForbidden();
    expect(Transaction::count())->toBe(0);
});

it('does not credit a deposit to an account with a credit block', function () {
    [$member, $checking] = verified();
    App\Models\AccountRestriction::create(['account_id' => $checking->id, 'type' => 'credit_block', 'reason_code' => 'account_review', 'locked_at' => now()]);

    checkPost($member, $checking)->assertSessionHas('error');
    expect(Deposit::count())->toBe(0);
});
