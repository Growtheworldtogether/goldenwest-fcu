<?php

use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\Card;
use App\Models\CardTransaction;
use App\Services\CardService;
use App\Services\Features;
use App\Support\CardNumber;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    seedReference();
    config(['bank.default_daily_limit' => 50_000_000, 'bank.per_transaction_limit' => 50_000_000]);
});

/** A verified member with $balance in checking and an active card. */
function cardMember(int $balance = 100_000): array
{
    [$u, $checking] = verifiedMember('Maya Brooks');
    $u->forceFill(['kyc_status' => 'verified'])->save();
    deposit($checking, $balance);
    $svc = app(CardService::class);
    $card = $svc->request($u->refresh(), $checking->id, null);
    $svc->decide($card, makeStaff('Manager'), true);

    return [$u->refresh(), $checking->refresh(), $card->refresh(), $svc];
}

it('lets a member request a card and shows only the request button until one exists', function () {
    [$u, $checking] = verifiedMember('Maya Brooks');
    $u->forceFill(['kyc_status' => 'verified'])->save();

    $this->actingAs($u)->get('/card')->assertOk()->assertSee('Request a virtual card');
    $this->actingAs($u)->post('/card', ['account_id' => $checking->id])->assertRedirect();
    expect(Card::count())->toBe(1)->and(Card::first()->status)->toBe('requested');
    $this->actingAs($u)->get('/card')->assertSee('Request under review')->assertDontSee('Card controls');
    $this->actingAs($u)->get('/card/pay')->assertNotFound();
});

it('refuses a card request until identity is verified, and a second open card', function () {
    [$u, $checking] = verifiedMember('Maya Brooks');
    $this->actingAs($u)->post('/card', ['account_id' => $checking->id])->assertSessionHasErrors('account_id');

    $u->forceFill(['kyc_status' => 'verified'])->save();
    $this->actingAs($u->refresh())->post('/card', ['account_id' => $checking->id])->assertSessionHasNoErrors();
    $this->actingAs($u)->post('/card', ['account_id' => $checking->id])->assertSessionHasErrors('account_id');
});

it('issues a card on approval with a valid fake number stored encrypted', function () {
    [$u, , $card] = cardMember();
    expect($card->status)->toBe('active')->and($card->last4)->toHaveLength(4);
    expect($card->getRawOriginal('number_encrypted'))->not->toContain($card->last4);
    $full = \Illuminate\Support\Facades\Crypt::decryptString($card->number_encrypted);
    expect(CardNumber::isValid($full))->toBeTrue()->and($full)->toStartWith('4')->toHaveLength(16);
    expect(AuditLog::where('action', 'card.approved')->count())->toBe(1);
});

it('records a reason when staff decline and shows it to the member', function () {
    [$u, $checking] = verifiedMember('Maya Brooks');
    $u->forceFill(['kyc_status' => 'verified'])->save();
    $card = app(CardService::class)->request($u->refresh(), $checking->id, null);
    $staff = makeStaff('Manager');

    $this->actingAs($staff)->post('/admin/cards/'.$card->id.'/decide', ['decision' => 'reject'])->assertSessionHasErrors('reason_text');
    $this->actingAs($staff)->post('/admin/cards/'.$card->id.'/decide', ['decision' => 'reject', 'reason_code' => 'account_review', 'reason_text' => 'Please call us first.'])->assertSessionHasNoErrors();
    expect($card->refresh()->status)->toBe('rejected');
    $this->actingAs($u)->get('/card')->assertSee('Please call us first.')->assertSee('Request a virtual card');
});

it('keeps card approval to staff with that permission', function () {
    [$u, $checking] = verifiedMember('Maya Brooks');
    $u->forceFill(['kyc_status' => 'verified'])->save();
    $card = app(CardService::class)->request($u->refresh(), $checking->id, null);

    $this->actingAs(makeStaff('Teller'))->get('/admin/cards')->assertForbidden();
    $this->actingAs(makeStaff('Support Agent'))->post('/admin/cards/'.$card->id.'/decide', ['decision' => 'approve'])->assertForbidden();
    $this->actingAs(makeStaff('Manager'))->get('/admin/cards')->assertOk()->assertSee('Maya Brooks');
});

it('requires the password to reveal the card and never puts the number in the page otherwise', function () {
    [$u, , $card] = cardMember();
    $full = \Illuminate\Support\Facades\Crypt::decryptString($card->number_encrypted);

    $this->actingAs($u)->get('/card')->assertOk()->assertDontSee($full)->assertDontSee(chunk_split($full, 4, ' '));
    $this->actingAs($u)->post('/card/'.$card->id.'/reveal', ['password' => 'wrong'])->assertSessionHasErrors('password', null, 'reveal');
    $this->actingAs($u)->followingRedirects()->post('/card/'.$card->id.'/reveal', ['password' => 'a-long-test-password-1'])->assertSee(trim(chunk_split($full, 4, ' ')));
    expect(AuditLog::where('action', 'card.revealed')->count())->toBe(1);
});

it('posts a purchase through the ledger and shows it in activity', function () {
    [$u, $checking, $card, $svc] = cardMember(100_000);
    $p = $svc->pay($card, 'Harbor Coffee Co.', Money::cents(1_250));

    expect($p->status)->toBe('settled')->and($checking->refresh()->balance->cents)->toBe(98_750)->and($checking->held_balance->cents)->toBe(0);
    expect($p->transaction->type->value)->toBe('card_purchase');
    $this->actingAs($u)->get('/activity')->assertSee('Harbor Coffee Co.');
});

it('holds funds on authorization and gives them back on release', function () {
    [, $checking, $card, $svc] = cardMember(100_000);
    $p = $svc->authorize($card, 'Redwood Fuel', Money::cents(4_000));

    expect($p->status)->toBe('authorized')->and($checking->refresh()->held_balance->cents)->toBe(4_000)->and($checking->available_balance->cents)->toBe(96_000)->and($checking->balance->cents)->toBe(100_000);
    $svc->release($p);
    expect($checking->refresh()->held_balance->cents)->toBe(0)->and($checking->available_balance->cents)->toBe(100_000)->and($p->refresh()->status)->toBe('released');

    $p2 = $svc->authorize($card, 'Redwood Fuel', Money::cents(4_000));
    $svc->settle($p2);
    expect($checking->refresh()->balance->cents)->toBe(96_000)->and($checking->held_balance->cents)->toBe(0);
});

it('declines a frozen card, then works again once unfrozen', function () {
    [$u, $checking, $card, $svc] = cardMember();
    $this->actingAs($u)->post('/card/'.$card->id.'/freeze', ['freeze' => 1])->assertRedirect();
    $p = $svc->pay($card->refresh(), 'Summit Books', Money::cents(1_000));
    expect($p->status)->toBe('declined')->and($p->decline_reason)->toBe('card_frozen')->and($checking->refresh()->balance->cents)->toBe(100_000);

    $this->actingAs($u)->post('/card/'.$card->id.'/freeze', ['freeze' => 0]);
    expect($svc->pay($card->refresh(), 'Summit Books', Money::cents(1_000))->status)->toBe('settled');
});

it('declines purchases over the limits or the available balance', function () {
    [$u, $checking, $card, $svc] = cardMember(30_000);

    expect($svc->pay($card, 'Alder & Finch Clothing', Money::cents(60_000))->decline_reason)->toBe('over_purchase_limit');
    $this->actingAs($u)->post('/card/'.$card->id.'/limits', ['daily_limit' => '200', 'purchase_limit' => '150'])->assertSessionHasNoErrors();
    $card->refresh();
    expect($svc->pay($card, 'Summit Books', Money::cents(16_000))->decline_reason)->toBe('over_purchase_limit');
    expect($svc->pay($card, 'Summit Books', Money::cents(14_000))->status)->toBe('settled');
    expect($svc->pay($card, 'Summit Books', Money::cents(10_000))->decline_reason)->toBe('over_daily_limit');

    [, , $poor, $svc2] = cardMember(1_000);
    expect($svc2->pay($poor, 'Summit Books', Money::cents(5_000))->decline_reason)->toBe('insufficient_funds');
});

it('stops a member raising limits above the maximum', function () {
    [$u, , $card] = cardMember();
    $this->actingAs($u)->post('/card/'.$card->id.'/limits', ['daily_limit' => '5000', 'purchase_limit' => '100'])->assertSessionHasErrors('daily_limit');
});

it('declines when the account is locked', function () {
    [$u, $checking, $card, $svc] = cardMember();
    app(\App\Services\RestrictionService::class)->lock(makeStaff('Manager'), $checking, null, 'full_lock', 'account_review', 'Review');
    expect($svc->pay($card, 'Summit Books', Money::cents(1_000))->decline_reason)->toBe('account_restricted');
});

it('replaces a card with a new number and cancels pending holds on the old one', function () {
    [$u, $checking, $card, $svc] = cardMember();
    $hold = $svc->authorize($card, 'Redwood Fuel', Money::cents(5_000));
    $this->actingAs($u)->post('/card/'.$card->id.'/replace')->assertRedirect();

    expect($card->refresh()->status)->toBe('cancelled')->and($hold->refresh()->status)->toBe('released')->and($checking->refresh()->held_balance->cents)->toBe(0);
    $new = Card::where('user_id', $u->id)->where('status', 'active')->firstOrFail();
    expect($new->id)->not->toBe($card->id)->and($new->last4)->not->toBe($card->last4);
    expect($svc->pay($card, 'Summit Books', Money::cents(1_000))->decline_reason)->toBe('card_not_active');
});

it('keeps a card private to its owner', function () {
    [, , $card] = cardMember();
    [$other] = verifiedMember('Noah Price');
    $this->actingAs($other)->post('/card/'.$card->id.'/freeze', ['freeze' => 1])->assertNotFound();
    $this->actingAs($other)->post('/card/'.$card->id.'/reveal', ['password' => 'a-long-test-password-1'])->assertNotFound();
    $this->actingAs($other)->post('/card/'.$card->id.'/cancel')->assertNotFound();
});

it('shows announcements to the right audience until dismissed or ended', function () {
    [$u] = verifiedMember('Maya Brooks');
    $staff = makeStaff('Super Admin');
    $this->actingAs($staff)->post('/admin/announcements', ['title' => 'Holiday hours', 'body' => 'Closed Thursday.', 'type' => 'info', 'audience' => 'everyone', 'dismissible' => 1])->assertSessionHasNoErrors();
    $this->actingAs($staff)->post('/admin/announcements', ['title' => 'Wire news', 'body' => 'New wire cutoff.', 'type' => 'warning', 'audience' => 'wires'])->assertSessionHasNoErrors();
    $this->actingAs($staff)->post('/admin/announcements', ['title' => 'Old news', 'body' => 'Gone.', 'type' => 'info', 'audience' => 'everyone', 'starts_at' => now()->subDays(3)->toDateTimeString(), 'ends_at' => now()->subDay()->toDateTimeString()]);

    $this->actingAs($u)->get('/dashboard')->assertSee('Holiday hours')->assertDontSee('Wire news')->assertDontSee('Old news');
    Features::set($u, 'wires', true, $staff);
    $this->actingAs($u)->get('/dashboard')->assertSee('Wire news');

    $a = Announcement::where('title', 'Holiday hours')->first();
    $this->actingAs($u)->post('/announcements/'.$a->id.'/dismiss')->assertRedirect();
    $this->actingAs($u)->get('/dashboard')->assertDontSee('Holiday hours');
});

it('keeps announcements to Super Admin, and does not let members dismiss a permanent one', function () {
    $this->actingAs(makeStaff('Manager'))->get('/admin/announcements')->assertForbidden();
    [$u] = verifiedMember('Maya Brooks');
    $a = Announcement::create(['title' => 'Must read', 'body' => 'x', 'type' => 'warning', 'audience' => 'everyone', 'dismissible' => false]);
    $this->actingAs($u)->post('/announcements/'.$a->id.'/dismiss')->assertForbidden();
});

it('lets a member lock their own accounts with their password and blocks transfers', function () {
    [$u, $checking, $savings] = verifiedMember('Maya Brooks');
    deposit($checking, 50_000);

    $this->actingAs($u)->post('/security-settings/lock', ['password' => 'nope'])->assertSessionHasErrors('password', null, 'lock');
    $this->actingAs($u)->post('/security-settings/lock', ['password' => 'a-long-test-password-1'])->assertRedirect();
    expect(\App\Models\AccountRestriction::active()->where('reason_code', 'member_request')->count())->toBe(2);
    $this->actingAs($u)->get('/security-settings')->assertSee('Your accounts are locked');
    $this->actingAs($u)->get('/dashboard')->assertSee('restriction');
});

it('signs out other devices only with the right password', function () {
    [$u] = verifiedMember('Maya Brooks');
    \DB::table('sessions')->insert(['id' => 'other-session-1', 'user_id' => $u->id, 'ip_address' => '203.0.113.9', 'user_agent' => 'Mozilla/5.0 (iPhone) Safari', 'payload' => '', 'last_activity' => now()->timestamp]);

    $this->actingAs($u)->post('/security-settings/sign-out-others', ['password' => 'bad'])->assertSessionHasErrors('password', null, 'devices');
    expect(\DB::table('sessions')->where('id', 'other-session-1')->exists())->toBeTrue();
    $this->actingAs($u)->post('/security-settings/sign-out-others', ['password' => 'a-long-test-password-1'])->assertRedirect();
    expect(\DB::table('sessions')->where('id', 'other-session-1')->exists())->toBeFalse();
});

function physicalAddress(array $over = []): array
{
    return $over + ['name_on_card' => 'Maya Brooks', 'address_line1' => '14 Orchard Lane', 'city' => 'Fresno', 'state' => 'ca', 'postal_code' => '93710'];
}

it('lets a member order a physical card, once at a time, and cancel before review', function () {
    [$u, , $card] = cardMember();
    $this->actingAs($u)->get('/card')->assertSee('Request physical card');

    $this->actingAs($u)->post('/card/'.$card->id.'/physical', physicalAddress(['postal_code' => '12']))->assertSessionHasErrors('postal_code');
    $this->actingAs($u)->post('/card/'.$card->id.'/physical', physicalAddress())->assertSessionHasNoErrors();
    $order = \App\Models\PhysicalCardOrder::firstOrFail();
    expect($order->status)->toBe('requested')->and($order->state)->toBe('CA');

    $this->actingAs($u)->post('/card/'.$card->id.'/physical', physicalAddress())->assertSessionHas('toast');
    expect(\App\Models\PhysicalCardOrder::count())->toBe(1);
    $this->actingAs($u)->get('/card')->assertSee('Under review')->assertSee('14 Orchard Lane');

    $this->actingAs($u)->post('/card/physical/'.$order->id.'/cancel');
    expect($order->refresh()->status)->toBe('cancelled');
});

it('ships or declines a physical card order for staff with approval rights', function () {
    [$u, , $card] = cardMember();
    $this->actingAs($u)->post('/card/'.$card->id.'/physical', physicalAddress());
    $order = \App\Models\PhysicalCardOrder::firstOrFail();

    $this->actingAs(makeStaff('Teller'))->post('/admin/cards/physical/'.$order->id.'/decide', ['decision' => 'approve'])->assertForbidden();
    $manager = makeStaff('Manager');
    $this->actingAs($manager)->get('/admin/cards?tab=physical')->assertOk()->assertSee('Maya Brooks');
    $this->actingAs($manager)->post('/admin/cards/physical/'.$order->id.'/decide', ['decision' => 'reject'])->assertSessionHasErrors('reason_text');
    $this->actingAs($manager)->post('/admin/cards/physical/'.$order->id.'/decide', ['decision' => 'approve'])->assertSessionHasNoErrors();
    expect($order->refresh()->status)->toBe('shipped');
    $this->actingAs($u)->get('/card')->assertSee('On its way');
});

it('keeps physical orders to the card owner', function () {
    [, , $card] = cardMember();
    [$other] = verifiedMember('Noah Price');
    $this->actingAs($other)->post('/card/'.$card->id.'/physical', physicalAddress())->assertNotFound();
});
