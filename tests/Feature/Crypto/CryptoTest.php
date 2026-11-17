<?php

use App\Models\AuditLog;
use App\Models\CryptoAsset;
use App\Models\CryptoLedgerEntry;
use App\Models\CryptoTransaction;
use App\Models\CryptoWallet;
use App\Services\CryptoService;
use App\Services\Features;
use App\Support\CryptoAmount;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const ETH_ADDR = '0x52908400098527886E0F7030069857D2E4169EE7';
const MY_ETH = '0x8617E340B3D01FA5F11F306F4090FD50E238070D';

beforeEach(fn () => seedReference());

function cryptoMember(bool $flag = true): array
{
    [$u] = verifiedMember('Nia Okafor');
    $staff = makeStaff('Manager');
    if ($flag) {
        Features::set($u, 'crypto', true, $staff);
    }

    return [$u->refresh(), $staff, CryptoAsset::where('symbol', 'ETH')->first(), app(CryptoService::class)];
}

/** A funded member: address set, 2 ETH recorded. */
function fundedCrypto(): array
{
    [$u, $staff, $eth, $svc] = cryptoMember();
    $svc->assignAddress($u, $eth, MY_ETH, null, $staff);
    $svc->recordFunding($u, $eth, CryptoAmount::parse('2', 8), $staff, 'Initial funding');

    return [$u->refresh(), $staff, $eth, $svc];
}

it('parses and formats amounts without floats', function () {
    expect(CryptoAmount::parse('0.25', 8))->toBe(25_000_000)
        ->and(CryptoAmount::format(25_000_000, 8))->toBe('0.25')
        ->and(CryptoAmount::format(150_000_000, 8))->toBe('1.50')
        ->and(CryptoAmount::format(1_234_567_890, 6))->toBe('1,234.56789');
    expect(fn () => CryptoAmount::parse('0.1234567891', 8))->toThrow(InvalidArgumentException::class);
    expect(fn () => CryptoAmount::parse('abc', 8))->toThrow(InvalidArgumentException::class);
});

it('keeps every crypto route out of reach without the flag', function () {
    [$u] = cryptoMember(false);
    foreach (['/crypto', '/crypto/fund', '/crypto/send'] as $path) {
        $this->actingAs($u)->get($path)->assertNotFound();
    }
    $this->actingAs($u)->post('/crypto/send', [])->assertNotFound();
    $this->actingAs($u)->post('/crypto/fund/address', [])->assertNotFound();
    $this->actingAs($u)->get('/dashboard')->assertOk()->assertDontSee('Crypto');
});

it('shows the menu item and the pages once the flag is on', function () {
    [$u] = cryptoMember();
    $this->actingAs($u)->get('/dashboard')->assertSee('Crypto');
    $this->actingAs($u)->get('/crypto')->assertOk()->assertSee('simulation');
    $this->actingAs($u)->get('/crypto/fund')->assertOk()->assertSee('Request a BTC address');
});

it('shows the address, a QR code and the network warning on the fund page', function () {
    [$u, $staff, $eth, $svc] = cryptoMember();
    $svc->assignAddress($u, $eth, MY_ETH, null, $staff);
    $this->actingAs($u)->get('/crypto/fund?asset='.$eth->id)->assertOk()
        ->assertSee(MY_ETH)->assertSee('<svg', false)->assertSee('cannot be recovered')->assertSee('Copy address');
});

it('lets a member request an address and staff fill it in', function () {
    [$u, $staff, $eth] = cryptoMember();
    $this->actingAs($u)->post('/crypto/fund/address', ['asset' => $eth->id])->assertRedirect();
    expect(\App\Models\CryptoAddress::where('user_id', $u->id)->where('status', 'requested')->count())->toBe(1);
    $this->actingAs($u)->post('/crypto/fund/address', ['asset' => $eth->id]);
    expect(\App\Models\CryptoAddress::where('user_id', $u->id)->count())->toBe(1);

    $this->actingAs($staff)->post("/admin/members/{$u->id}/crypto/address", ['asset_id' => $eth->id, 'address' => 'not-an-address'])->assertSessionHas('toast');
    expect(app(CryptoService::class)->activeAddress($u, $eth))->toBeNull();
    $this->actingAs($staff)->post("/admin/members/{$u->id}/crypto/address", ['asset_id' => $eth->id, 'address' => MY_ETH])->assertSessionHas('toast.type', 'ok');
    expect(app(CryptoService::class)->activeAddress($u, $eth)->address)->toBe(MY_ETH)
        ->and(\App\Models\CryptoAddress::where('user_id', $u->id)->count())->toBe(1);
});

it('credits a wallet only when staff confirm a declared deposit, and writes a ledger row', function () {
    [$u, $staff, $eth, $svc] = cryptoMember();
    $svc->assignAddress($u, $eth, MY_ETH, null, $staff);

    $this->actingAs($u)->post('/crypto/fund', ['asset' => $eth->id, 'amount' => '1.5', 'token' => (string) Str::ulid()])->assertRedirect();
    $tx = CryptoTransaction::where('type', 'fund')->first();
    expect($tx->status)->toBe('pending')->and(CryptoWallet::where('user_id', $u->id)->first()?->balance ?? 0)->toBe(0);

    $this->actingAs($staff)->post("/admin/crypto/deposits/{$tx->reference}", ['action' => 'confirm', 'amount' => '1.25'])->assertSessionHas('toast.type', 'ok');
    $w = CryptoWallet::where('user_id', $u->id)->first();
    expect($w->balance)->toBe(125_000_000)
        ->and($tx->refresh()->status)->toBe('completed')->and($tx->tx_hash)->toStartWith('0x')
        ->and(CryptoLedgerEntry::where('wallet_id', $w->id)->first()->balance_after)->toBe(125_000_000)
        ->and(AuditLog::where('action', 'crypto.deposit_confirmed')->exists())->toBeTrue();

    // A second confirmation does nothing.
    $this->actingAs($staff)->post("/admin/crypto/deposits/{$tx->reference}", ['action' => 'confirm'])->assertSessionHas('toast.type', 'bad');
    expect($w->refresh()->balance)->toBe(125_000_000);
});

it('does not take a deposit declaration without an address', function () {
    [$u, , $eth] = cryptoMember();
    $this->actingAs($u)->post('/crypto/fund', ['asset' => $eth->id, 'amount' => '1', 'token' => (string) Str::ulid()])->assertSessionHasErrors('amount');
});

it('sets the amount and fee aside when a send is requested', function () {
    [$u, , $eth, $svc] = fundedCrypto();
    $tx = $svc->send($u, $eth, ETH_ADDR, null, CryptoAmount::parse('0.5', 8), (string) Str::ulid());
    $w = CryptoWallet::where('user_id', $u->id)->first();
    expect($tx->status)->toBe('pending')->and($tx->fee)->toBe($eth->network_fee)
        ->and($w->balance)->toBe(200_000_000)->and($w->held)->toBe(50_000_000 + $eth->network_fee)
        ->and($w->available())->toBe(200_000_000 - 50_000_000 - $eth->network_fee);
});

it('refuses a send that is over the available balance, to a bad address, or to the member\'s own address', function () {
    [$u, , $eth, $svc] = fundedCrypto();
    expect(fn () => $svc->send($u, $eth, ETH_ADDR, null, CryptoAmount::parse('1.99', 8), (string) Str::ulid()))->not->toThrow(Exception::class);
    expect(fn () => $svc->send($u, $eth, ETH_ADDR, null, CryptoAmount::parse('1', 8), (string) Str::ulid()))->toThrow(InvalidArgumentException::class, 'available balance');
    expect(fn () => $svc->send($u, $eth, 'nope', null, 1, (string) Str::ulid()))->toThrow(InvalidArgumentException::class, 'not a valid');
    expect(fn () => $svc->send($u, $eth, MY_ETH, null, 1, (string) Str::ulid()))->toThrow(InvalidArgumentException::class, 'your own');
});

it('treats a repeated token as the same send', function () {
    [$u, , $eth, $svc] = fundedCrypto();
    $token = (string) Str::ulid();
    $a = $svc->send($u, $eth, ETH_ADDR, null, 10_000_000, $token);
    $b = $svc->send($u, $eth, ETH_ADDR, null, 10_000_000, $token);
    expect($b->id)->toBe($a->id)->and(CryptoWallet::first()->held)->toBe(10_000_000 + $eth->network_fee);
});

it('approves a send: deducts amount and fee, makes a hash and a ledger row', function () {
    [$u, $staff, $eth, $svc] = fundedCrypto();
    $tx = $svc->send($u, $eth, ETH_ADDR, null, 50_000_000, (string) Str::ulid());
    $this->actingAs($staff)->post("/admin/crypto/sends/{$tx->reference}", ['action' => 'approve'])->assertSessionHas('toast.type', 'ok');
    $w = CryptoWallet::first();
    expect($tx->refresh()->status)->toBe('completed')->and($tx->tx_hash)->toStartWith('0x')->and(strlen($tx->tx_hash))->toBe(66)
        ->and($w->balance)->toBe(200_000_000 - 50_000_000 - $eth->network_fee)->and($w->held)->toBe(0)
        ->and($tx->entries()->first()->direction)->toBe('debit')
        ->and($tx->decisions()->first()->decision)->toBe('approved');
});

it('returns the amount when a send is rejected, with a reason the member can read', function () {
    [$u, $staff, $eth, $svc] = fundedCrypto();
    $tx = $svc->send($u, $eth, ETH_ADDR, null, 50_000_000, (string) Str::ulid());
    $this->actingAs($staff)->post("/admin/crypto/sends/{$tx->reference}", ['action' => 'reject'])->assertSessionHas('toast.type', 'bad'); // reason required
    $this->actingAs($staff)->post("/admin/crypto/sends/{$tx->reference}", ['action' => 'reject', 'reason_code' => 'suspected_fraud', 'reason_text' => 'We could not verify the receiving address.'])->assertSessionHas('toast.type', 'ok');
    $w = CryptoWallet::first();
    expect($w->balance)->toBe(200_000_000)->and($w->held)->toBe(0)->and($tx->refresh()->status)->toBe('rejected')
        ->and($tx->entries()->count())->toBe(0);
    $this->actingAs($u)->get('/crypto')->assertSee('We could not verify the receiving address.')->assertSee('Declined');
});

it('keeps a held send set aside until staff decide', function () {
    [$u, $staff, $eth, $svc] = fundedCrypto();
    $tx = $svc->send($u, $eth, ETH_ADDR, null, 50_000_000, (string) Str::ulid());
    $this->actingAs($staff)->post("/admin/crypto/sends/{$tx->reference}", ['action' => 'hold', 'reason_code' => 'account_review', 'reason_text' => 'Reviewing the account.'])->assertSessionHas('toast.type', 'ok');
    expect($tx->refresh()->status)->toBe('held')->and(CryptoWallet::first()->held)->toBeGreaterThan(0);
    $this->actingAs($staff)->post("/admin/crypto/sends/{$tx->reference}", ['action' => 'approve']);
    expect($tx->refresh()->status)->toBe('completed')->and($tx->decisions()->count())->toBe(2);
});

it('lets a member cancel a send that is still waiting', function () {
    [$u, $staff, $eth, $svc] = fundedCrypto();
    $tx = $svc->send($u, $eth, ETH_ADDR, null, 50_000_000, (string) Str::ulid());
    $this->actingAs($u)->post("/crypto/transactions/{$tx->reference}/cancel")->assertSessionHas('toast.type', 'ok');
    expect($tx->refresh()->status)->toBe('cancelled')->and(CryptoWallet::first()->held)->toBe(0);
    $this->actingAs($staff)->post("/admin/crypto/sends/{$tx->reference}", ['action' => 'approve'])->assertSessionHas('toast.type', 'bad');
});

it('stops staff deciding on a send they started while acting as the member', function () {
    [$u, $staff, $eth, $svc] = fundedCrypto();
    $tx = $svc->send($u, $eth, ETH_ADDR, null, 50_000_000, (string) Str::ulid(), $staff->id);
    $this->actingAs($staff)->post("/admin/crypto/sends/{$tx->reference}", ['action' => 'approve'])->assertSessionHas('toast.type', 'bad');
    expect($tx->refresh()->status)->toBe('pending');
});

it('needs the member\'s password on the send form', function () {
    [$u, , $eth] = fundedCrypto();
    $post = fn ($pw) => $this->actingAs($u)->post('/crypto/send', ['asset' => $eth->id, 'address' => ETH_ADDR, 'amount' => '0.1', 'understand' => 1, 'password' => $pw, 'token' => (string) Str::ulid()]);
    $post('wrong-password')->assertSessionHasErrors('password');
    expect(CryptoTransaction::where('type', 'send')->count())->toBe(0);
    $post('a-long-test-password-1')->assertRedirect('/crypto')->assertSessionHas('toast.type', 'ok');
    expect(CryptoTransaction::where('type', 'send')->count())->toBe(1);
});

it('adjusts a wallet with a ledger row and refuses to go below what is held', function () {
    [$u, $staff, $eth, $svc] = fundedCrypto();
    $admin = makeStaff('Super Admin');
    $svc->send($u, $eth, ETH_ADDR, null, 150_000_000, (string) Str::ulid());
    $this->actingAs($admin)->post("/admin/members/{$u->id}/crypto/adjust", ['asset_id' => $eth->id, 'direction' => 'debit', 'amount' => '1', 'description' => 'Correction', 'reason_code' => 'error_correction'])->assertSessionHas('toast.type', 'bad');
    $this->actingAs($admin)->post("/admin/members/{$u->id}/crypto/adjust", ['asset_id' => $eth->id, 'direction' => 'credit', 'amount' => '0.5', 'description' => 'Goodwill credit', 'reason_code' => 'goodwill'])->assertSessionHas('toast.type', 'ok');
    expect(CryptoWallet::first()->balance)->toBe(250_000_000)
        ->and(CryptoTransaction::where('type', 'adjustment')->first()->entries()->first()->balance_after)->toBe(250_000_000);
});

it('keeps crypto ledger rows append-only', function () {
    fundedCrypto();
    $row = CryptoLedgerEntry::first();
    expect(fn () => $row->update(['amount' => 1]))->toThrow(LogicException::class);
    expect(fn () => $row->delete())->toThrow(LogicException::class);
});

it('blocks staff without the approval permission from the console', function () {
    [$u] = cryptoMember();
    $agent = makeStaff('Support Agent');
    $this->actingAs($agent)->get('/admin/crypto')->assertForbidden();
    $this->actingAs($u)->get('/admin/crypto')->assertStatus(403);
});

it('lets the right staff see the console, the Member 360 panel and the assets tab', function () {
    [$u, $staff] = fundedCrypto();
    $this->actingAs($staff)->get('/admin/crypto')->assertOk();
    $this->actingAs($staff)->get('/admin/crypto?tab=funding')->assertOk();
    $this->actingAs($staff)->get('/admin/crypto?tab=activity')->assertOk()->assertSee('Nia Okafor');
    $this->actingAs($staff)->get("/admin/members/{$u->id}")->assertOk()->assertSee('Crypto wallet')->assertSee('Set a deposit address');
    $super = makeStaff('Super Admin');
    $this->actingAs($super)->get('/admin/crypto?tab=assets')->assertOk()->assertSee('Network fee');
    $eth = CryptoAsset::where('symbol', 'ETH')->first();
    $this->actingAs($super)->put("/admin/crypto/assets/{$eth->id}", ['network_fee' => '0.001', 'usd_rate' => '3000', 'enabled' => 1])->assertSessionHas('toast.type', 'ok');
    expect($eth->refresh()->network_fee)->toBe(100_000)->and($eth->usd_rate_cents)->toBe(300_000);
});

it('turns the crypto flag into an audit row and an announcement audience', function () {
    [$u] = cryptoMember();
    expect(AuditLog::where('action', 'feature.enabled')->where('new->feature', 'crypto')->exists())->toBeTrue()
        ->and(Features::ALL)->toHaveKey('crypto');
});
