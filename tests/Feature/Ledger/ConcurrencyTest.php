<?php

use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * These tests run real, separate PHP processes against one SQLite file so that the requests
 * genuinely collide. In-memory tests cannot prove that locking works.
 */

beforeEach(function () {
    $this->dbFile = sys_get_temp_dir().'/gw-ledger-'.uniqid().'.sqlite';
    touch($this->dbFile);
    config(['database.connections.sqlite.database' => $this->dbFile]);
    DB::purge('sqlite');
    Artisan::call('migrate', ['--force' => true]);
    seedReference();
    config(['bank.default_daily_limit' => 100_000_000, 'bank.per_transaction_limit' => 100_000_000]);
});

afterEach(function () {
    DB::disconnect('sqlite');
    foreach (['', '-wal', '-shm'] as $suffix) {
        @unlink($this->dbFile.$suffix);
    }
});

/** Start every request at once and collect what each one reported. @param list<array{string,int,int,int,string}> $jobs */
function runTogether(string $dbFile, array $jobs): array
{
    $startAt = microtime(true) + 2.0;
    $env = array_merge(getenv(), ['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $dbFile, 'DB_URL' => '', 'APP_ENV' => 'testing', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array']);
    $running = [];

    foreach ($jobs as [$type, $from, $to, $cents, $key]) {
        $cmd = [PHP_BINARY, __DIR__.'/../../Support/transfer_worker.php', $type, (string) $from, (string) $to, (string) $cents, $key, (string) $startAt];
        $process = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), $env);
        $running[] = [$process, $pipes];
    }

    $results = [];
    foreach ($running as [$process, $pipes]) {
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        proc_close($process);
        $results[] = json_decode($out, true) ?? ['ok' => false, 'reason' => 'no output: '.$err];
    }

    return $results;
}

it('never overdraws an account when 20 transfers arrive at the same moment', function () {
    $checking = makeAccount(null, 100_000);
    $savings = makeAccount(null, 0, 'GOLDEN-SAVINGS');

    $jobs = [];
    for ($i = 0; $i < 20; $i++) {
        $jobs[] = ['transfer', $checking->id, $savings->id, 15_000, "race-{$i}"];
    }
    $results = runTogether($this->dbFile, $jobs);

    $errors = array_filter($results, fn ($r) => str_starts_with($r['reason'] ?? '', 'error') || str_starts_with($r['reason'] ?? '', 'no output'));
    expect($errors)->toBeEmpty();

    $succeeded = count(array_filter($results, fn ($r) => $r['ok']));
    $refused = array_filter($results, fn ($r) => ! $r['ok']);
    expect($succeeded)->toBe(6)
        ->and(array_unique(array_column($refused, 'reason')))->toBe(['insufficient_funds']);

    expect($checking->refresh()->balance->cents)->toBe(10_000)
        ->and($savings->refresh()->balance->cents)->toBe(90_000)
        ->and(Transaction::where('type', 'transfer')->count())->toBe(6);

    $this->artisan('ledger:verify')->assertSuccessful();
})->group('concurrency');

it('moves money exactly once when the same idempotency key arrives 10 times at once', function () {
    $checking = makeAccount(null, 100_000);
    $savings = makeAccount(null, 0, 'GOLDEN-SAVINGS');

    $jobs = array_fill(0, 10, ['transfer', $checking->id, $savings->id, 5_000, 'double-click']);
    $results = runTogether($this->dbFile, $jobs);

    expect(array_unique(array_column($results, 'ok')))->toBe([true])
        ->and(count(array_unique(array_column($results, 'id'))))->toBe(1);

    expect($checking->refresh()->balance->cents)->toBe(95_000)
        ->and(Transaction::where('idempotency_key', 'double-click')->count())->toBe(1);

    $this->artisan('ledger:verify')->assertSuccessful();
})->group('concurrency');

it('handles transfers crossing in opposite directions without deadlock or lost money', function () {
    $a = makeAccount(null, 200_000);
    $b = makeAccount(null, 200_000, 'GOLDEN-SAVINGS');

    $jobs = [];
    for ($i = 0; $i < 10; $i++) {
        $jobs[] = ['transfer', $a->id, $b->id, 10_000, "ab-{$i}"];
        $jobs[] = ['transfer', $b->id, $a->id, 7_000, "ba-{$i}"];
    }
    $results = runTogether($this->dbFile, $jobs);

    expect(array_filter($results, fn ($r) => ! $r['ok']))->toBeEmpty();
    expect($a->refresh()->balance->cents + $b->refresh()->balance->cents)->toBe(400_000)
        ->and($a->balance->cents)->toBe(200_000 - 100_000 + 70_000);

    $this->artisan('ledger:verify')->assertSuccessful();
})->group('concurrency');

it('lets overdraft protection and a purchase race without taking more than the savings hold', function () {
    $user = makeUser();
    $savings = makeAccount($user, 30_000, 'GOLDEN-SAVINGS');
    $checking = makeAccount($user, 0, overdraftId: $savings->id);

    $jobs = [];
    for ($i = 0; $i < 12; $i++) {
        $jobs[] = ['card_purchase', $checking->id, 0, 5_000, "buy-{$i}"];
    }
    $results = runTogether($this->dbFile, $jobs);

    expect(count(array_filter($results, fn ($r) => $r['ok'])))->toBe(6);
    expect($savings->refresh()->balance->cents)->toBe(0)->and($checking->refresh()->balance->cents)->toBe(0);
    expect(Account::where('balance', '<', 0)->count())->toBe(0);

    $this->artisan('ledger:verify')->assertSuccessful();
})->group('concurrency');
