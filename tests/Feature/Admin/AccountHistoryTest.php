<?php

use App\Models\AuditLog;
use App\Models\LedgerEntry;
use App\Models\Transaction;
use App\Services\HistoryBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => seedReference());

function history(array $over = []): array
{
    return array_merge(['volume' => 'medium', 'target' => '2500.00', 'from' => now()->subMonths(6)->toDateString(), 'to' => now()->subDay()->toDateString(), 'backdate_open' => '1', 'note' => 'Opening history for the account.'], $over);
}

it('builds history that never goes negative, stays inside the dates and ends on the exact target', function (string $kind, int $target, int $days, int $seed) {
    $from = CarbonImmutable::parse('2026-03-01');
    $to = $from->addDays($days);
    $rows = HistoryBuilder::build($kind, $from, $to, $target, $seed);
    $balance = 0;
    foreach ($rows as $r) {
        $balance += $r['direction'] === 'credit' ? $r['amount'] : -$r['amount'];
        expect($balance)->toBeGreaterThanOrEqual(0)->and($r['amount'])->toBeGreaterThan(0)
            ->and($r['at']->toDateString() >= $from->toDateString() && $r['at']->toDateString() <= $to->toDateString())->toBeTrue();
    }
    expect($balance)->toBe($target)->and(count($rows))->toBeGreaterThan(0);
    expect(HistoryBuilder::build($kind, $from, $to, $target, $seed))->toEqual($rows);
})->with(function () {
    foreach (['checking', 'savings'] as $kind) {
        foreach ([[5_000, 3], [250_000, 180], [4_000_000, 365], [12_345_67, 45], [50_000, 0]] as $i => [$t, $d]) {
            foreach ([1, 7, 99] as $seed) {
                yield "$kind $t over $d days seed $seed" => [$kind, $t, $d, $seed];
            }
        }
    }
});

it('lets staff add history to a new account, balances reconcile, and the audit log records it', function () {
    [$member, $checking] = verifiedMember('Maria Alvarez');
    $staff = makeStaff('Super Admin');
    $this->actingAs($staff)->post('/admin/members/'.$member->id.'/history', history(['account_id' => $checking->id]))->assertSessionHasNoErrors();

    $checking->refresh();
    expect($checking->balance->cents)->toBe(250_000)->and($checking->available_balance->cents)->toBe(250_000)
        ->and(Transaction::where('to_account_id', $checking->id)->orWhere('from_account_id', $checking->id)->where('memo', 'like', 'Opening history%')->count())->toBeGreaterThan(5)
        ->and($checking->created_at->toDateString())->toBe(now()->subMonths(6)->toDateString());
    $last = LedgerEntry::where('account_id', $checking->id)->orderByDesc('id')->first();
    expect($last->balance_after->cents)->toBe(250_000);
    expect(LedgerEntry::where('account_id', $checking->id)->orderBy('id')->pluck('created_at')->map->timestamp->all())->toBe(LedgerEntry::where('account_id', $checking->id)->orderBy('created_at')->orderBy('id')->pluck('created_at')->map->timestamp->all());
    expect(AuditLog::where('action', 'account.history_added')->count())->toBe(1);
    $this->artisan('ledger:verify')->assertSuccessful();
});

it('refuses an account that already has activity, and a second run', function () {
    [$member, $checking] = verifiedMember('Maria Alvarez');
    deposit($checking, 10_000);
    $staff = makeStaff('Super Admin');
    $this->actingAs($staff)->post('/admin/members/'.$member->id.'/history', history(['account_id' => $checking->id]))->assertSessionHasErrors('account_id');
    expect($checking->refresh()->balance->cents)->toBe(10_000);
});

it('checks the dates, the amount and who may do it', function () {
    [$member, $checking, $savings] = verifiedMember('Maria Alvarez');
    $staff = makeStaff('Super Admin');
    $url = '/admin/members/'.$member->id.'/history';
    $this->actingAs($staff)->post($url, history(['account_id' => $checking->id, 'to' => now()->subMonths(7)->toDateString()]))->assertSessionHasErrors('to');
    $this->actingAs($staff)->post($url, history(['account_id' => $checking->id, 'to' => now()->addDays(3)->toDateString()]))->assertSessionHasErrors('to');
    $this->actingAs($staff)->post($url, history(['account_id' => $checking->id, 'target' => '10.00']))->assertSessionHasErrors('target');
    $this->actingAs($member)->post($url, history(['account_id' => $checking->id]))->assertForbidden();
    // A Manager is held to the adjustment limit.
    $manager = makeStaff('Manager', true, 'Limited Manager');
    $this->actingAs($manager)->post($url, history(['account_id' => $checking->id, 'target' => '9000.00']))->assertForbidden();
    expect(Transaction::count())->toBe(0);
    $this->actingAs($staff)->post($url, history(['account_id' => $savings->id, 'target' => '9000.00']))->assertSessionHasNoErrors();
    expect($savings->refresh()->balance->cents)->toBe(900_000)->and(Transaction::where('type', 'dividend')->count())->toBeGreaterThan(0);
});

it('has no limit on how far back history can start, and still lands on the balance', function () {
    [$member, $checking] = verifiedMember('Maria Alvarez');
    $this->actingAs(makeStaff('Super Admin'))->post('/admin/members/'.$member->id.'/history', history(['account_id' => $checking->id, 'from' => '2005-03-01', 'target' => '40000.00']))->assertSessionHasNoErrors();
    expect($checking->refresh()->balance->cents)->toBe(4_000_000)->and($checking->created_at->toDateString())->toBe('2005-03-01');
    $this->artisan('ledger:verify')->assertSuccessful();
});

it('never shows staff notes or the history marker to the member', function () {
    [$member, $checking] = verifiedMember('Maria Alvarez');
    $staff = makeStaff('Super Admin');
    $this->actingAs($staff)->post('/admin/members/'.$member->id.'/history', history(['account_id' => $checking->id]))->assertSessionHasNoErrors();
    $tx = Transaction::where('memo', 'like', 'Opening history%')->first();
    expect($tx->memberMemo())->toBeNull();

    $this->actingAs($member)->get('/accounts/'.$checking->id)->assertOk()->assertDontSee('Opening history');
    $this->actingAs($member)->get('/transactions/'.$tx->reference)->assertOk()->assertDontSee('Opening history')->assertDontSee('Memo');
    $this->actingAs($member)->get('/accounts/'.$checking->id.'?q=Opening+history')->assertOk()->assertDontSee('Direct deposit');
});

it('makes fewer or more transactions on request and still lands on the balance', function (string $kind) {
    $from = CarbonImmutable::parse('2026-01-01');
    $to = CarbonImmutable::parse('2026-10-31');
    $counts = [];
    foreach (['few', 'medium', 'many'] as $volume) {
        $rows = HistoryBuilder::build($kind, $from, $to, 800_000, 5, $volume);
        $balance = 0;
        foreach ($rows as $r) {
            $balance += $r['direction'] === 'credit' ? $r['amount'] : -$r['amount'];
            expect($balance)->toBeGreaterThanOrEqual(0);
        }
        expect($balance)->toBe(800_000);
        $counts[$volume] = count($rows);
    }
    expect($counts['few'])->toBeLessThan($counts['medium'])->and($counts['medium'])->toBeLessThan($counts['many']);
})->with(['checking', 'savings']);

it('requires a valid amount choice', function () {
    [$member, $checking] = verifiedMember('Maria Alvarez');
    $this->actingAs(makeStaff('Super Admin'))->post('/admin/members/'.$member->id.'/history', history(['account_id' => $checking->id, 'volume' => 'lots']))->assertSessionHasErrors('volume');
});
