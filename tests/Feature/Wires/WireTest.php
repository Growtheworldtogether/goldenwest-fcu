<?php

use App\Models\AuditLog;
use App\Models\Payee;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
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

/** A verified member with wires on, $balance in checking and a ready recipient. */
function wireMember(int $balance = 1_000_000, string $name = 'Maria Alvarez'): array
{
    [$member, $checking] = verifiedMember($name);
    $member->forceFill(['kyc_status' => 'verified'])->save();
    Features::set($member, 'wires', true, makeStaff('Manager', true, 'Setup Manager'));
    deposit($checking, $balance);
    $payee = Payee::create(['user_id' => $member->id, 'name' => 'Pacific Title Co', 'bank_name' => 'Harbor Bank', 'routing_number' => '021000021', 'account_number' => '998877665544', 'last4' => '5544', 'account_type' => 'checking', 'activates_at' => now()->subHour()]);

    return [$member->refresh(), $checking->refresh(), $payee];
}

function sendWire(User $member, $account, Payee $payee, string $amount = '5000.00', array $extra = [])
{
    return test()->actingAs($member)->post('/wires', array_merge([
        'token' => (string) Str::ulid(), 'account_id' => $account->id, 'payee_id' => $payee->id, 'amount' => $amount,
        'recipient_address' => '100 Main St, Reno, NV 89501', 'purpose' => 'Home closing costs', 'understand' => '1',
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
    [$member, $checking, $payee] = wireMember();
    Features::set($member, 'wires', false, makeStaff('Manager', true, 'Other Manager'));

    expect(fn () => app(WireService::class)->send($member, $checking, $payee, Money::cents(100_000), '1 A St, Reno, NV', 'Rent', (string) Str::ulid()))->toThrow(TransferException::class);
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
    $this->actingAs($manager)->post("/admin/members/{$member->id}/features/crypto", ['enabled' => 1])->assertNotFound();
});

it('sets the amount and fee aside and waits for review', function () {
    [$member, $checking, $payee] = wireMember(1_000_000);

    sendWire($member, $checking, $payee, '5000.00')->assertRedirect();

    $tx = Transaction::where('type', 'wire_out')->first();
    $wire = WireTransfer::first();
    expect($tx->status->value)->toBe('pending')->and($tx->amount->cents)->toBe(500_000)->and($tx->fee->cents)->toBe(2_500)->and($tx->payee_id)->toBe($payee->id);
    expect($checking->refresh()->balance->cents)->toBe(1_000_000)->and($checking->available_balance->cents)->toBe(497_500)->and($checking->held_balance->cents)->toBe(502_500);
    expect($wire->purpose)->toBe('Home closing costs')->and($wire->process_on->toDateString())->toBe('2026-11-18');
    $this->actingAs($member)->get('/wires')->assertSee('In review')->assertSee('Pacific Title Co');
});

it('posts amount and fee as separate ledger lines when staff approve', function () {
    [$member, $checking, $payee] = wireMember(1_000_000);
    sendWire($member, $checking, $payee, '5000.00');
    $tx = Transaction::where('type', 'wire_out')->first();

    $this->actingAs(makeStaff('Manager'))->post("/admin/queue/{$tx->reference}/approve")->assertRedirect();

    expect($tx->refresh()->status->value)->toBe('completed')->and($checking->refresh()->balance->cents)->toBe(497_500)->and($checking->held_balance->cents)->toBe(0);
    expect($tx->entries->map(fn ($e) => $e->amount->cents)->all())->toBe([500_000, 2_500]);
    $this->artisan('ledger:verify')->assertSuccessful();
});

it('releases the amount and the fee when a wire is rejected, shows the reason, and allows a contest', function () {
    [$member, $checking, $payee] = wireMember(1_000_000);
    sendWire($member, $checking, $payee, '5000.00');
    $tx = Transaction::where('type', 'wire_out')->first();

    $this->actingAs(makeStaff('Compliance'))->post("/admin/queue/{$tx->reference}/reject", ['reason_code' => 'verification_required', 'reason_text' => 'We could not confirm the recipient by phone.'])->assertRedirect();

    expect($checking->refresh()->available_balance->cents)->toBe(1_000_000)->and($checking->held_balance->cents)->toBe(0);
    $this->actingAs($member)->get("/transactions/{$tx->reference}")->assertSee('We could not confirm the recipient by phone.')->assertSee('Contest this decision');
    $this->actingAs($member)->post("/transactions/{$tx->reference}/contest", [])->assertRedirect();
});

it('refuses a wire when the amount plus the fee is more than the available balance', function () {
    [$member, $checking, $payee] = wireMember(500_000);

    sendWire($member, $checking, $payee, '5000.00')->assertSessionHas('error');
    expect(Transaction::where('type', 'wire_out')->count())->toBe(0)->and(WireTransfer::count())->toBe(0);
});

it('blocks a new recipient during the cooling-off period and an unverified member', function () {
    [$member, $checking, $payee] = wireMember();
    $payee->update(['activates_at' => now()->addHours(5)]);
    sendWire($member, $checking, $payee)->assertSessionHas('error');

    $payee->update(['activates_at' => now()->subHour()]);
    $member->forceFill(['kyc_status' => 'pending'])->save();
    sendWire($member->refresh(), $checking, $payee)->assertSessionHas('error');
    expect(Transaction::where('type', 'wire_out')->count())->toBe(0);
});

it('does not let a member wire to someone else\'s saved recipient', function () {
    [$member, $checking] = wireMember();
    [, , $theirs] = wireMember(10_000, 'Sam Rivera');

    sendWire($member, $checking, $theirs)->assertNotFound();
    expect(Transaction::where('type', 'wire_out')->count())->toBe(0);
});

it('requires the address, purpose and the acknowledgement', function () {
    [$member, $checking, $payee] = wireMember();

    sendWire($member, $checking, $payee, '100.00', ['recipient_address' => ''])->assertSessionHasErrors('recipient_address');
    sendWire($member, $checking, $payee, '100.00', ['purpose' => ''])->assertSessionHasErrors('purpose');
    sendWire($member, $checking, $payee, '100.00', ['understand' => null])->assertSessionHasErrors('understand');
    expect(Transaction::where('type', 'wire_out')->count())->toBe(0);
});

it('sends a wire only once when the form is submitted twice', function () {
    [$member, $checking, $payee] = wireMember();
    $token = (string) Str::ulid();

    sendWire($member, $checking, $payee, '200.00', ['token' => $token]);
    sendWire($member, $checking, $payee, '200.00', ['token' => $token]);

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
    [$member, $checking, $payee] = wireMember(1_000_000);

    $this->actingAs(makeStaff('Manager'))->get('/admin/settings')->assertForbidden();
    $super = makeStaff('Super Admin', true, 'Super Person');
    $this->actingAs($super)->put('/admin/settings', ['wire_fee' => '35.00', 'wire_cutoff' => '12:30'])->assertSessionHasNoErrors();
    expect(WireService::fee()->cents)->toBe(3_500)->and(WireService::cutoff())->toBe('12:30');
    expect(AuditLog::where('action', 'setting.changed')->where('actor_id', $super->id)->latest('id')->first()->new)->toBe(['wires' => ['fee' => 3_500, 'cutoff' => '12:30']]);

    sendWire($member, $checking, $payee, '1000.00');
    expect(Transaction::where('type', 'wire_out')->first()->fee->cents)->toBe(3_500);
    $this->actingAs($super)->put('/admin/settings', ['wire_fee' => '-5'])->assertSessionHasErrors('wire_fee');
});

it('shows staff the wire details and keeps them from other members', function () {
    [$member, $checking, $payee] = wireMember();
    sendWire($member, $checking, $payee, '750.00');
    $tx = Transaction::where('type', 'wire_out')->first();
    [$other] = verifiedMember('Sam Rivera');

    $this->actingAs(makeStaff('Manager'))->get("/admin/queue/{$tx->reference}")->assertOk()->assertSee('Wire details')->assertSee('100 Main St, Reno, NV 89501')->assertSee('021000021')->assertSee('ending 5544')->assertDontSee('998877665544');
    $this->actingAs($other)->get("/transactions/{$tx->reference}")->assertForbidden();
});
