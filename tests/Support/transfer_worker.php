<?php

// Run by the concurrency tests as a separate process: php transfer_worker.php <type> <from> <to> <cents> <key> <startAt>
use App\Enums\TransactionType;
use App\Services\Ledger\TransferException;
use App\Services\Ledger\TransferRequest;
use App\Services\Ledger\TransferService;
use App\Support\Money;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

[, $type, $from, $to, $cents, $key, $startAt] = $argv;

while (microtime(true) < (float) $startAt) {
    usleep(500); // line everyone up so the requests really collide
}

try {
    $tx = app(TransferService::class)->execute(new TransferRequest(
        type: TransactionType::from($type),
        amount: Money::cents((int) $cents),
        idempotencyKey: $key,
        fromAccountId: (int) $from ?: null,
        toAccountId: (int) $to ?: null,
    ));
    echo json_encode(['ok' => true, 'id' => $tx->id]);
} catch (TransferException $e) {
    echo json_encode(['ok' => false, 'reason' => $e->reason]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'reason' => 'error: '.$e->getMessage()]);
}
