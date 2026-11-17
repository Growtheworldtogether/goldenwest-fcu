<?php

use App\Models\AuditLog;
use App\Models\Payee;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WireRecipient;
use App\Models\WireTransfer;
use App\Services\Features;
use App\Services\Ledger\TransferException;
use App\Services\WireService;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    seedReference();
    config(['bank.default_daily_limit' => 50_000_000, 'bank.per_transaction_limit' => 50_000_000, 'bank.wire_fee' => 2_500]);
    $this->travelTo(Carbon::parse('2026-11-18 10:00', 'America/Los_Angeles')); // a Wednesday morning, before the cutoff
});

/** A verified member with wires on and $balance in checking. */
function wireMember(int $balance = 1_000_000, string $name = 'Maria Alvarez'): array
{
    [$member, $checking] = verifiedMember($name);
    $member->forceFill(['kyc_status' => 'verified'])->save();
    Features::set($member, 'wires', true, makeStaff('Manager', true, 'Setup Manager'));
    deposit($checking, $balance);

    return [$member->refresh(), $checking->refresh()];
}

/** A wire form typed in full, as a member would. */
function sendWire(User $member, $account, string $amount = '5000.00', array $extra = [])
{
    return test()->actingAs($member)->post('/wires', array_merge([
        'token' => (string) Str::ulid(), 'account_id' => $account->id, 'recipient_id' => 'new', 'amount' => $amount,
        'recipient_name' => 'Pacific Title Co', 'recipient_address' => '100 Main St, Reno, NV 89501', 'bank_name' => 'Harbor Bank',
        'routing_number' => '021000021', 'account_number' => '998877665544', 'account_number_confirmation' => '998877665544',
        'purpose' => 'Home closing costs', 'understand' => '1',
    ], $extra));
}

it('returns 404 for every wire page when the member does not have the feature', function () {
    [$member, $checking] = verifiedMember();
    $member->forceFill(['kyc_status' => 'verified'])->save();

    $this->actingAs($member)->get('/wires')->assertNotFound();
    $this->actingAs($member)->get('/wires/new')->assertNotFound();
    $this->actingAs($member)->post('/wires', ['token' => (string) Str::ulid()])->assertNotFound();
    $this->actingAs($member)->get('/dashboard')->assertOk()->assertDontSee('href="'.url('/wires').'"', false);
});

it('refuses a wire at the service level too when the feature is off', function () {
    [$member, $checking] = wireMember();
    Features::set($member, 'wires', false, makeStaff('Manager', true, 'Other Manager'));

    expect(fn () => app(WireService::class)->send($member, $checking, ['name' => 'A', 'address' => '1 A St, Reno, NV', 'bank_name' => 'B', 'routing_number' => '021000021', 'account_number' => '1234567'], Money::cents(100_000), 'Rent', null, (string) Str::ulid()))->toThrow(TransferException::class);
    expect(Transaction::where('type', 'wire_out')->count())->toBe(0);
});

it('lets a manager turn wires on and off for a member, records it, and keeps tellers out', function () {
    [$member] = verifiedMember();
    $manager = makeStaff('Manager');

    $this->actingAs(makeStaff('Teller'))->post("/admin/members/{$member->id}/features/wires", ['enabled' => 1])->assertForbidden();
    $this->actingAs($manager)->post("/admin/members/{$member->id}/features/wires", ['enabled' => 1, 'note' => 'Home purchase'])->assertRedirect();
    expect(Features::enabled($member, 'wires'))->toBeTrue();
    $this->actingAs($member->refresh())->get('/wires')->assertOk();

    $this->actingAs($manager)->post("/admin/members/{$member->id}/features/wires", ['enabled' => 0])->assertRedirect();
    expect(Features::enabled($member, 'wires'))->toBeFalse();
    $this->actingAs($member)->get('/wires')->assertNotFound();
    expect(AuditLog::whereIn('action', ['feature.enabled', 'feature.disabled'])->where('actor_id', $manager->id)->count())->toBe(2);
    $this->actingAs($manager)->post("/admin/members/{$member->id}/features/jetpack", ['enabled' => 1])->assertNotFound();
});

it('sets the amount and fee aside and waits for review', function () {
    [$member, $checking] = wireMember(1_000_000);

    sendWire($member, $checking, '5000.00')->assertRedirect();

    $tx = Transaction::where('type', 'wire_out')->first();
    $wire = WireTransfer::first();
    expect($tx->status->value)->toBe('pending')->and($tx->amount->cents)->toBe(500_000)->and($tx->fee->cents)->toBe(2_500)->and($tx->fee->cents)->toBe(2_500);
    expect($checking->refresh()->balance->cents)->toBe(1_000_000)->and($checking->available_balance->cents)->toBe(497_500)->and($checking->held_balance->cents)->toBe(502_500);
    expect($tx->payee_id)->toBeNull()->and($wire->recipient_name)->toBe('Pacific Title Co')->and($wire->bank_name)->toBe('Harbor Bank')->and($wire->purpose)->toBe('Home closing costs')->and($wire->process_on->toDateString())->toBe('2026-11-18');
    $this->actingAs($member)->get('/wires')->assertSee('In review')->assertSee('Pacific Title Co');
    $this->actingAs($member)->get('/wires/new')->assertOk()->assertSee('full name');
});

it('posts amount and fee as separate ledger lines when staff approve', function () {
    [$member, $checking] = wireMember(1_000_000);
    sendWire($member, $checking, '5000.00');
    $tx = Transaction::where('type', 'wire_out')->first();

    $this->actingAs(makeStaff('Manager'))->post("/admin/queue/{$tx->reference}/approve")->assertRedirect();

    expect($tx->refresh()->status->value)->toBe('completed')->and($checking->refresh()->balance->cents)->toBe(497_500)->and($checking->held_balance->cents)->toBe(0);
    expect($tx->entries->map(fn ($e) => $e->amount->cents)->all())->toBe([500_000, 2_500]);
    $this->artisan('ledger:verify')->assertSuccessful();
});

it('releases the amount and the fee when a wire is rejected, shows the reason, and allows a contest', function () {
    [$member, $checking] = wireMember(1_000_000);
    sendWire($member, $checking, '5000.00');
    $tx = Transaction::where('type', 'wire_out')->first();

    $this->actingAs(makeStaff('Compliance'))->post("/admin/queue/{$tx->reference}/reject", ['reason_code' => 'verification_required', 'reason_text' => 'We could not confirm the recipient by phone.'])->assertRedirect();

    expect($checking->refresh()->available_balance->cents)->toBe(1_000_000)->and($checking->held_balance->cents)->toBe(0);
    $this->actingAs($member)->get("/transactions/{$tx->reference}")->assertSee('We could not confirm the recipient by phone.')->assertSee('Contest this decision');
    $this->actingAs($member)->post("/transactions/{$tx->reference}/contest", [])->assertRedirect();
});

it('refuses a wire when the amount plus the fee is more than the available balance', function () {
    [$member, $checking] = wireMember(500_000);

    sendWire($member, $checking, '5000.00')->assertSessionHas('error');
    expect(Transaction::where('type', 'wire_out')->count())->toBe(0)->and(WireTransfer::count())->toBe(0);
});

it('blocks an unverified member and checks the routing number', function () {
    [$member, $checking] = wireMember();
    sendWire($member, $checking, '100.00', ['routing_number' => '123456789'])->assertSessionHasErrors('routing_number');

    $member->forceFill(['kyc_status' => 'pending'])->save();
    sendWire($member->refresh(), $checking)->assertSessionHas('error');
    expect(Transaction::where('type', 'wire_out')->count())->toBe(0);
});

it('has its own recipient form and does not use the saved ACH accounts', function () {
    [$member, $checking] = wireMember();
    Payee::create(['user_id' => $member->id, 'name' => 'Savings at Harbor', 'bank_name' => 'Harbor Bank', 'routing_number' => '021000021', 'account_number' => '5551234567', 'last4' => '4567', 'account_type' => 'savings', 'activates_at' => now()->subDay()]);

    $this->actingAs($member)->get('/wires/new')->assertOk()->assertSee('full name')->assertSee('Purpose of the wire')->assertDontSee('Savings at Harbor');
    sendWire($member, $checking, '100.00', ['recipient_id' => (string) Payee::first()->id])->assertNotFound(); // an ACH account id is not a wire recipient
    sendWire($member, $checking, '100.00', ['recipient_name' => '', 'bank_name' => '', 'routing_number' => '', 'account_number' => ''])->assertSessionHasErrors(['recipient_name', 'bank_name', 'routing_number', 'account_number']);
    expect(Transaction::where('type', 'wire_out')->count())->toBe(0);
});

it('keeps the account number encrypted and saves a recipient only when asked', function () {
    [$member, $checking] = wireMember();

    sendWire($member, $checking, '100.00');
    expect(WireRecipient::count())->toBe(0);
    $raw = DB::table('wire_transfers')->value('account_number');
    expect($raw)->not->toContain('998877665544')->and(WireTransfer::first()->account_number)->toBe('998877665544')->and(WireTransfer::first()->last4)->toBe('5544');

    sendWire($member, $checking, '100.00', ['save_recipient' => '1']);
    sendWire($member, $checking, '100.00', ['save_recipient' => '1']);
    expect(WireRecipient::count())->toBe(1);
    expect(DB::table('wire_recipients')->value('account_number'))->not->toContain('998877665544');
    $this->actingAs($member)->get('/wires')->assertSee('Saved recipients')->assertSee('ending 5544')->assertDontSee('998877665544');
});

it('lets a member wire to a saved recipient, and never to someone else\'s', function () {
    [$member, $checking] = wireMember();
    [$other, $otherChecking] = wireMember(500_000, 'Sam Rivera');
    sendWire($other, $otherChecking, '100.00', ['save_recipient' => '1']);
    sendWire($member, $checking, '100.00', ['save_recipient' => '1']);
    $mine = WireRecipient::where('user_id', $member->id)->first();
    $theirs = WireRecipient::where('user_id', $other->id)->first();

    $this->actingAs($member)->post('/wires', ['token' => (string) Str::ulid(), 'account_id' => $checking->id, 'recipient_id' => (string) $mine->id, 'amount' => '250.00', 'purpose' => 'Rent', 'understand' => '1'])->assertRedirect();
    expect(WireTransfer::where('user_id', $member->id)->latest('id')->first()->recipient_name)->toBe('Pacific Title Co');

    $this->actingAs($member)->post('/wires', ['token' => (string) Str::ulid(), 'account_id' => $checking->id, 'recipient_id' => (string) $theirs->id, 'amount' => '250.00', 'purpose' => 'Rent', 'understand' => '1'])->assertNotFound();
    $this->actingAs($member)->delete("/wires/recipients/{$theirs->id}")->assertNotFound();
    $this->actingAs($member)->delete("/wires/recipients/{$mine->id}")->assertRedirect();
    expect($mine->refresh()->status)->toBe('removed');
});

it('tells staff when it is the first wire to an account', function () {
    [$member, $checking] = wireMember(2_000_000);
    sendWire($member, $checking, '300.00');
    $first = Transaction::where('type', 'wire_out')->first();
    $manager = makeStaff('Manager');
    $this->actingAs($manager)->get("/admin/queue/{$first->reference}")->assertSee('First wire to this account');
    $this->actingAs($manager)->post("/admin/queue/{$first->reference}/approve");

    sendWire($member, $checking, '400.00');
    $second = Transaction::where('type', 'wire_out')->latest('id')->first();
    $this->actingAs($manager)->get("/admin/queue/{$second->reference}")->assertSee('Sent 1 wire here before')->assertDontSee('First wire to this account');
});

it('requires the address, purpose and the acknowledgement', function () {
    [$member, $checking] = wireMember();

    sendWire($member, $checking, '100.00', ['recipient_address' => ''])->assertSessionHasErrors('recipient_address');
    sendWire($member, $checking, '100.00', ['purpose' => ''])->assertSessionHasErrors('purpose');
    sendWire($member, $checking, '100.00', ['understand' => null])->assertSessionHasErrors('understand');
    expect(Transaction::where('type', 'wire_out')->count())->toBe(0);
});

it('sends a wire only once when the form is submitted twice', function () {
    [$member, $checking] = wireMember();
    $token = (string) Str::ulid();

    sendWire($member, $checking, '200.00', ['token' => $token]);
    sendWire($member, $checking, '200.00', ['token' => $token]);

    expect(Transaction::where('type', 'wire_out')->count())->toBe(1)->and(WireTransfer::count())->toBe(1)->and($checking->refresh()->held_balance->cents)->toBe(22_500);
});

it('picks the send day from the cutoff and skips weekends', function () {
    $cases = [
        ['2026-11-18 10:00', '2026-11-18'], // Wednesday before 2 pm
        ['2026-11-18 14:01', '2026-11-19'], // Wednesday after the cutoff
        ['2026-11-20 15:00', '2026-11-23'], // Friday after the cutoff goes to Monday
        ['2026-11-21 09:00', '2026-11-23'], // Saturday
        ['2026-11-22 09:00', '2026-11-23'], // Sunday
    ];
    foreach ($cases as [$when, $expected]) {
        expect(WireService::processOn(Carbon::parse($when, 'America/Los_Angeles'))->toDateString())->toBe($expected);
    }
});

it('lets only a Super Admin change the fee and cutoff, uses them on the next wire, and audits it', function () {
    [$member, $checking] = wireMember(1_000_000);

    $this->actingAs(makeStaff('Manager'))->get('/admin/settings')->assertForbidden();
    $super = makeStaff('Super Admin', true, 'Super Person');
    $this->actingAs($super)->put('/admin/settings', ['wire_fee' => '35.00', 'wire_cutoff' => '12:30'])->assertSessionHasNoErrors();
    expect(WireService::fee()->cents)->toBe(3_500)->and(WireService::cutoff())->toBe('12:30');
    expect(AuditLog::where('action', 'setting.changed')->where('actor_id', $super->id)->latest('id')->first()->new)->toBe(['wires' => ['fee' => 3_500, 'cutoff' => '12:30']]);

    sendWire($member, $checking, '1000.00');
    expect(Transaction::where('type', 'wire_out')->first()->fee->cents)->toBe(3_500);
    $this->actingAs($super)->put('/admin/settings', ['wire_fee' => '-5'])->assertSessionHasErrors('wire_fee');
});

it('shows staff the wire details and keeps them from other members', function () {
    [$member, $checking] = wireMember();
    sendWire($member, $checking, '750.00');
    $tx = Transaction::where('type', 'wire_out')->first();
    [$other] = verifiedMember('Sam Rivera');

    $this->actingAs(makeStaff('Manager'))->get("/admin/queue/{$tx->reference}")->assertOk()->assertSee('Wire details')->assertSee('100 Main St, Reno, NV 89501')->assertSee('021000021')->assertSee('ending 5544')->assertDontSee('998877665544')->assertSee('Harbor Bank');
    $this->actingAs($other)->get("/transactions/{$tx->reference}")->assertForbidden();
});
