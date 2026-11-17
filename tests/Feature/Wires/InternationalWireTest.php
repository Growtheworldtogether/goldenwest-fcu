<?php

use App\Models\User;
use App\Models\WireRecipient;
use App\Models\WireTransfer;
use App\Services\Features;
use App\Support\Iban;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    seedReference();
    config(['bank.default_daily_limit' => 50_000_000, 'bank.per_transaction_limit' => 50_000_000, 'bank.wire_fee' => 2_500, 'bank.wire_fee_international' => 4_500]);
    $this->travelTo(Carbon::parse('2026-11-18 10:00', 'America/Los_Angeles'));
});

function intlMember(bool $intl = true): array
{
    [$member, $checking] = verifiedMember('Maria Alvarez');
    $member->forceFill(['kyc_status' => 'verified'])->save();
    $staff = makeStaff('Manager', true, 'Setup Manager');
    Features::set($member, 'wires', true, $staff);
    if ($intl) {
        Features::set($member, 'wires_international', true, $staff);
    }
    deposit($checking, 1_000_000);

    return [$member->refresh(), $checking->refresh()];
}

function sendIntl(User $member, $account, array $extra = [])
{
    return test()->actingAs($member)->post('/wires', array_merge([
        'token' => (string) Str::ulid(), 'account_id' => $account->id, 'recipient_id' => 'new', 'amount' => '1000.00', 'kind' => 'international',
        'recipient_name' => 'Hans Becker', 'recipient_address' => 'Berlin, Germany', 'bank_name' => 'Deutsche Bank', 'country' => 'DE',
        'swift' => 'DEUTDEFF', 'account_number' => 'DE89 3704 0044 0532 0130 00', 'account_number_confirmation' => 'DE89 3704 0044 0532 0130 00',
        'purpose' => 'Family support', 'understand' => '1', 'save_recipient' => '1',
    ], $extra));
}

it('checks IBANs by country and check digits', function () {
    expect(Iban::valid('GB82 WEST 1234 5698 7654 32', 'GB'))->toBeTrue()
        ->and(Iban::valid('DE89370400440532013000', 'DE'))->toBeTrue()
        ->and(Iban::valid('DE89370400440532013001', 'DE'))->toBeFalse()
        ->and(Iban::valid('DE89370400440532013000', 'GB'))->toBeFalse();
});

it('sends an international wire with the international fee and records country and SWIFT', function () {
    [$member, $checking] = intlMember();
    sendIntl($member, $checking)->assertSessionHasNoErrors()->assertRedirect();

    $wire = WireTransfer::firstOrFail();
    expect($wire->kind)->toBe('international')->and($wire->country)->toBe('DE')->and($wire->swift)->toBe('DEUTDEFF')
        ->and($wire->routing_number)->toBeNull()->and($wire->last4)->toBe('3000')
        ->and($wire->transaction->fee->cents ?? 4_500)->toBe(4_500);
    expect(WireRecipient::firstOrFail()->isInternational())->toBeTrue();
    expect($checking->refresh()->available_balance->cents ?? null)->not->toBeNull();
});

it('hides international wires from members without the feature', function () {
    [$member, $checking] = intlMember(false);
    sendIntl($member, $checking)->assertSessionHas('error');
    expect(WireTransfer::count())->toBe(0);
});

it('refuses a SWIFT code from another country, a bad IBAN and blocked countries', function () {
    [$member, $checking] = intlMember();
    sendIntl($member, $checking, ['swift' => 'BARCGB22'])->assertSessionHasErrors('swift');
    sendIntl($member, $checking, ['account_number' => 'DE89370400440532013001', 'account_number_confirmation' => 'DE89370400440532013001'])->assertSessionHasErrors('account_number');
    sendIntl($member, $checking, ['country' => 'IR', 'swift' => 'BMJIIRTH'])->assertSessionHasErrors('country');
    sendIntl($member, $checking, ['country' => 'RU', 'swift' => 'SABRRUMM'])->assertSessionHasErrors('country');
    expect(WireTransfer::count())->toBe(0);
});

it('still sends a domestic wire at the domestic fee for a member with both', function () {
    [$member, $checking] = intlMember();
    $this->actingAs($member)->post('/wires', [
        'token' => (string) Str::ulid(), 'account_id' => $checking->id, 'recipient_id' => 'new', 'amount' => '500.00', 'kind' => 'domestic',
        'recipient_name' => 'Pacific Title Co', 'recipient_address' => '100 Main St, Reno, NV 89501', 'bank_name' => 'Harbor Bank',
        'routing_number' => '021000021', 'account_number' => '998877665544', 'account_number_confirmation' => '998877665544',
        'purpose' => 'Closing', 'understand' => '1',
    ])->assertSessionHasNoErrors();
    expect(WireTransfer::firstOrFail()->kind)->toBe('domestic');
});

it('shows the new wire form with the international choice only when allowed', function () {
    [$member] = intlMember();
    $this->actingAs($member)->get('/wires/new')->assertOk()->assertSee('A bank in another country');
    [$other] = intlMember(false);
});
