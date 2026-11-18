<?php

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Support\Money;
use App\Services\Ledger\TransferRequest;
use App\Services\Ledger\TransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => seedReference());

function memberTransfer(int $cents, ?int $impersonator, ?int $by = null, string $type = 'transfer'): Transaction
{
    [$member, $checking, $savings] = verifiedMember('Maria Alvarez');
    $checking->forceFill(['balance' => 5_000_000, 'available_balance' => 5_000_000, 'daily_limit' => 9_000_000])->save();

    return app(TransferService::class)->execute(new TransferRequest(
        type: TransactionType::from($type), amount: Money::cents($cents), idempotencyKey: 'sa:'.uniqid(), fromAccountId: $checking->id,
        toAccountId: $type === 'wire_out' ? null : $savings->id, initiatedBy: $by ?? $member->id, impersonatorId: $impersonator,
    ));
}

it('holds a large member transfer for review, as before', function () {
    expect(memberTransfer(600_000, null)->status->value)->toBe('pending');
});

it('posts at once when a Super Admin is acting as the member', function () {
    $tx = memberTransfer(600_000, makeStaff('Super Admin')->id);
    expect($tx->status->value)->toBe('completed')->and($tx->impersonator_id)->not->toBeNull();
});

it('still holds the transfer when a Manager is acting as the member', function () {
    expect(memberTransfer(600_000, makeStaff('Manager')->id)->status->value)->toBe('pending');
});

it('lets a Super Admin send a wire without waiting', function () {
    $tx = memberTransfer(300_000, makeStaff('Super Admin')->id, type: 'wire_out');
    expect($tx->status->value)->toBe('completed');
});

it('still refuses what the account cannot cover, even for a Super Admin', function () {
    [$member, $checking, $savings] = verifiedMember();
    expect(fn () => app(TransferService::class)->execute(new TransferRequest(
        type: TransactionType::Transfer, amount: Money::cents(600_000), idempotencyKey: 'sa:'.uniqid(), fromAccountId: $checking->id,
        toAccountId: $savings->id, initiatedBy: $member->id, impersonatorId: makeStaff('Super Admin')->id,
    )))->toThrow(App\Services\Ledger\TransferException::class);
});

it('needs a second approver for $25,000 and over, unless the approver is a Super Admin', function () {
    $held = memberTransfer(2_500_000, null);
    expect($held->status->value)->toBe('pending');

    $ledger = app(TransferService::class);
    $ledger->approve($held, makeStaff('Manager', name: 'Daniel Brooks'));
    expect($held->refresh()->status->value)->toBe('pending');

    $other = memberTransfer(2_500_000, null);
    $ledger->approve($other, makeStaff('Super Admin', name: 'Alexandra Reyes'));
    expect($other->refresh()->status->value)->toBe('completed');
});

it('lets a Super Admin approve something they started, but nobody else', function () {
    $super = makeStaff('Super Admin');
    $own = memberTransfer(600_000, makeStaff('Manager', name: 'Daniel Brooks')->id);
    expect($own->status->value)->toBe('pending');
    $manager = makeStaff('Compliance', name: 'Rachel Kim');
    $mine = memberTransfer(600_000, null, by: $manager->id);
    expect(fn () => app(TransferService::class)->approve($mine, $manager))->toThrow(App\Services\Ledger\TransferException::class, 'cannot approve');
});

it('records every Super Admin action in the ledger and the audit log, with who acted', function () {
    $super = makeStaff('Super Admin');
    $tx = memberTransfer(600_000, $super->id);
    expect(App\Models\AuditLog::where('action', 'transaction.posted')->where('impersonator_id', $super->id)->exists())->toBeTrue()
        ->and(Transaction::whereKey($tx->id)->where('impersonator_id', $super->id)->exists())->toBeTrue();
});

it('records a cash deposit of $10,000 or more by a Super Admin at once, but a teller waits for a second person', function () {
    [$member, $checking] = verifiedMember();
    $deposits = app(App\Services\DepositService::class);
    $teller = makeStaff('Teller', name: 'Tina Lopez');
    $d1 = $deposits->cash($teller, $checking, Money::cents(1_200_000), null, 'a');
    expect($d1->transaction->status->value)->toBe('pending');
    $d2 = $deposits->cash(makeStaff('Super Admin', name: 'Alexandra Reyes'), $checking, Money::cents(1_200_000), null, 'b');
    expect($d2->transaction->status->value)->toBe('completed');
});
