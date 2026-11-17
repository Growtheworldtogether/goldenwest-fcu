<?php

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\AccountRestriction;
use App\Models\AuditLog;
use App\Models\Decision;
use App\Models\LedgerEntry;
use App\Models\Transaction;
use App\Services\Ledger\TransferException;
use App\Services\Ledger\TransferRequest;
use App\Services\Ledger\TransferService;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    seedReference();
    config(['bank.default_daily_limit' => 20_000_000]);
});

function service(): TransferService
{
    return app(TransferService::class);
}

function refused(callable $fn): string
{
    try {
        $fn();
    } catch (TransferException $e) {
        return $e->reason;
    }

    return 'not refused';
}

it('moves money between accounts with a debit and a credit entry', function () {
    $user = makeUser();
    $from = makeAccount($user, 100_000);
    $to = makeAccount($user, 5_000, 'GOLDEN-SAVINGS');

    $tx = transfer($from, $to, 25_000, by: $user->id);

    expect($tx->status)->toBe(TransactionStatus::Completed)
        ->and($from->refresh()->balance->cents)->toBe(75_000)
        ->and($from->available_balance->cents)->toBe(75_000)
        ->and($to->refresh()->balance->cents)->toBe(30_000);

    $entries = LedgerEntry::where('transaction_id', $tx->id)->orderBy('id')->get();
    expect($entries)->toHaveCount(2)
        ->and($entries[0]->direction)->toBe('debit')->and($entries[0]->balance_after->cents)->toBe(75_000)
        ->and($entries[1]->direction)->toBe('credit')->and($entries[1]->balance_after->cents)->toBe(30_000);
    expect(AuditLog::where('action', 'transaction.posted')->where('subject_id', $tx->id)->exists())->toBeTrue();
});

it('records single-sided movements for money entering or leaving the credit union', function () {
    $account = makeAccount(null, 50_000);

    service()->execute(new TransferRequest(type: TransactionType::CardPurchase, amount: Money::cents(6_792), idempotencyKey: 'p1', fromAccountId: $account->id, memberDescription: 'Whole Foods Market'));

    expect($account->refresh()->balance->cents)->toBe(43_208)
        ->and(LedgerEntry::where('account_id', $account->id)->count())->toBe(2);
});

it('charges a fee as a second debit entry', function () {
    $from = makeAccount(null, 50_000);
    $to = makeAccount(null, 0, 'GOLDEN-SAVINGS');

    service()->execute(new TransferRequest(type: TransactionType::Transfer, amount: Money::cents(10_000), fee: Money::cents(500), idempotencyKey: 'fee1', fromAccountId: $from->id, toAccountId: $to->id));

    expect($from->refresh()->balance->cents)->toBe(39_500)->and($to->refresh()->balance->cents)->toBe(10_000);
});

it('refuses a transfer larger than the available balance and moves nothing', function () {
    $from = makeAccount(null, 10_000);
    $to = makeAccount(null, 0, 'GOLDEN-SAVINGS');

    expect(refused(fn () => transfer($from, $to, 10_001)))->toBe('insufficient_funds');
    expect($from->refresh()->balance->cents)->toBe(10_000)->and(Transaction::where('type', 'transfer')->count())->toBe(0);
});

it('refuses bad shapes', function () {
    $a = makeAccount(null, 10_000);

    expect(refused(fn () => transfer($a, $a, 100)))->toBe('invalid_accounts');
    expect(refused(fn () => service()->execute(new TransferRequest(type: TransactionType::Deposit, amount: Money::cents(0), idempotencyKey: 'z', toAccountId: $a->id))))->toBe('invalid_amount');
    expect(refused(fn () => service()->execute(new TransferRequest(type: TransactionType::Deposit, amount: Money::cents(-5), idempotencyKey: 'z2', toAccountId: $a->id))))->toBe('invalid_amount');
    expect(refused(fn () => service()->execute(new TransferRequest(type: TransactionType::Transfer, amount: Money::cents(100), idempotencyKey: 'z3', fromAccountId: $a->id))))->toBe('invalid_accounts');
});

it('moves money once when the same idempotency key is sent again', function () {
    $from = makeAccount(null, 100_000);
    $to = makeAccount(null, 0, 'GOLDEN-SAVINGS');

    $first = transfer($from, $to, 20_000, 'same-key');
    $second = transfer($from, $to, 20_000, 'same-key');

    expect($second->id)->toBe($first->id)
        ->and($from->refresh()->balance->cents)->toBe(80_000)
        ->and(Transaction::where('idempotency_key', 'same-key')->count())->toBe(1);
});

it('refuses a reused key for a different request', function () {
    $from = makeAccount(null, 100_000);
    $to = makeAccount(null, 0, 'GOLDEN-SAVINGS');
    transfer($from, $to, 20_000, 'k');

    expect(refused(fn () => transfer($from, $to, 30_000, 'k')))->toBe('idempotency_conflict');
});

it('blocks a debit from a debit-blocked account even when the service is called directly', function () {
    $from = makeAccount(null, 100_000);
    $to = makeAccount(null, 0, 'GOLDEN-SAVINGS');
    AccountRestriction::create(['account_id' => $from->id, 'type' => 'debit_block', 'reason_code' => 'account_review', 'locked_at' => now()]);

    expect(refused(fn () => transfer($from, $to, 100)))->toBe('account_locked');
    expect(refused(fn () => transfer($to, $from, 0 + 100)))->toBe('insufficient_funds'); // credits are still allowed
});

it('allows credits to a debit-blocked account and blocks credits to a credit-blocked one', function () {
    $a = makeAccount(null, 100_000);
    $b = makeAccount(null, 100_000, 'GOLDEN-SAVINGS');
    AccountRestriction::create(['account_id' => $a->id, 'type' => 'debit_block', 'reason_code' => 'account_review', 'locked_at' => now()]);
    expect(transfer($b, $a, 500)->status)->toBe(TransactionStatus::Completed);

    AccountRestriction::create(['account_id' => $b->id, 'type' => 'credit_block', 'reason_code' => 'account_review', 'locked_at' => now()]);
    expect(refused(fn () => transfer($a, $b, 500)))->toBe('account_locked');
});

it('honours a full lock on the member and stops honouring it once unlocked', function () {
    $user = makeUser();
    $from = makeAccount($user, 100_000);
    $to = makeAccount(null, 0, 'GOLDEN-SAVINGS');
    $lock = AccountRestriction::create(['user_id' => $user->id, 'type' => 'full_lock', 'reason_code' => 'suspected_fraud', 'locked_at' => now()]);

    expect(refused(fn () => transfer($from, $to, 100)))->toBe('account_locked');
    expect(refused(fn () => transfer($to, $from, 100)))->toBe('account_locked');

    $lock->update(['unlocked_at' => now(), 'unlock_note' => 'Verified by phone']);
    expect(transfer($from, $to, 100)->status)->toBe(TransactionStatus::Completed);
});

it('refuses when the member is suspended or temporarily locked out', function () {
    $user = makeUser();
    $from = makeAccount($user, 100_000);
    $to = makeAccount(null, 0, 'GOLDEN-SAVINGS');

    $user->forceFill(['locked_until' => now()->addHour()])->save();
    expect(refused(fn () => transfer($from, $to, 100)))->toBe('member_locked');

    $user->forceFill(['locked_until' => null, 'status' => 'suspended'])->save();
    expect(refused(fn () => transfer($from, $to, 100)))->toBe('member_locked');
});

it('enforces the per-transaction and daily limits', function () {
    $from = makeAccount(null, 9_000_000);
    $to = makeAccount(null, 0, 'GOLDEN-SAVINGS');

    expect(refused(fn () => transfer($from, $to, 2_600_000)))->toBe('limit_exceeded');

    $from->update(['daily_limit' => 300_000]);
    transfer($from, $to, 200_000);
    expect(refused(fn () => transfer($from, $to, 150_000)))->toBe('daily_limit_exceeded');
    expect(transfer($from, $to, 100_000)->status)->toBe(TransactionStatus::Completed);
});

it('covers a shortfall from the linked savings account as its own transaction', function () {
    $user = makeUser();
    $savings = makeAccount($user, 80_000, 'GOLDEN-SAVINGS');
    $checking = makeAccount($user, 10_000, overdraftId: $savings->id);
    $other = makeAccount(null, 0);

    service()->execute(new TransferRequest(type: TransactionType::CardPurchase, amount: Money::cents(25_000), idempotencyKey: 'od1', fromAccountId: $checking->id, memberDescription: 'Delta Air Lines'));

    expect($checking->refresh()->balance->cents)->toBe(0)
        ->and($savings->refresh()->balance->cents)->toBe(65_000);

    $od = Transaction::where('type', 'overdraft_transfer')->first();
    expect($od->amount->cents)->toBe(15_000)->and($od->from_account_id)->toBe($savings->id)->and($od->to_account_id)->toBe($checking->id);
});

it('adds the overdraft fee when one is configured', function () {
    config(['bank.overdraft_transfer_fee' => 1_200]);
    $user = makeUser();
    $savings = makeAccount($user, 80_000, 'GOLDEN-SAVINGS');
    $checking = makeAccount($user, 10_000, overdraftId: $savings->id);

    service()->execute(new TransferRequest(type: TransactionType::CardPurchase, amount: Money::cents(25_000), idempotencyKey: 'od2', fromAccountId: $checking->id));

    expect($checking->refresh()->balance->cents)->toBe(0)
        ->and($savings->refresh()->balance->cents)->toBe(80_000 - 15_000 - 1_200)
        ->and(Transaction::where('type', 'fee')->count())->toBe(1);
});

it('refuses when checking and linked savings together cannot cover it', function () {
    $user = makeUser();
    $savings = makeAccount($user, 5_000, 'GOLDEN-SAVINGS');
    $checking = makeAccount($user, 10_000, overdraftId: $savings->id);

    expect(refused(fn () => service()->execute(new TransferRequest(type: TransactionType::CardPurchase, amount: Money::cents(25_000), idempotencyKey: 'od3', fromAccountId: $checking->id))))->toBe('insufficient_funds');
    expect($savings->refresh()->balance->cents)->toBe(5_000)->and(Transaction::where('type', 'overdraft_transfer')->count())->toBe(0);
});

it('reserves a large transfer for review and keeps the money out of reach', function () {
    $from = makeAccount(null, 2_000_000);
    $to = makeAccount(null, 0, 'GOLDEN-SAVINGS');

    $tx = transfer($from, $to, 600_000);

    expect($tx->status)->toBe(TransactionStatus::Pending)
        ->and($from->refresh()->balance->cents)->toBe(2_000_000)
        ->and($from->held_balance->cents)->toBe(600_000)
        ->and($from->available_balance->cents)->toBe(1_400_000)
        ->and($to->refresh()->balance->cents)->toBe(0);

    // The reserved money cannot be spent twice
    expect(refused(fn () => transfer($from, $to, 1_450_000)))->toBe('insufficient_funds');
});

it('posts on approval and releases the reserve on rejection', function () {
    $admin = makeUser('admin');
    $from = makeAccount(null, 2_000_000);
    $to = makeAccount(null, 0, 'GOLDEN-SAVINGS');

    $a = transfer($from, $to, 600_000);
    $approved = service()->approve($a, $admin);
    expect($approved->status)->toBe(TransactionStatus::Completed)
        ->and($from->refresh()->balance->cents)->toBe(1_400_000)->and($from->held_balance->cents)->toBe(0)
        ->and($to->refresh()->balance->cents)->toBe(600_000);

    $b = transfer($from, $to, 700_000);
    $rejected = service()->reject($b, $admin, 'unusual_activity', 'This payment is unlike your normal activity.');
    expect($rejected->status)->toBe(TransactionStatus::Rejected)
        ->and($from->refresh()->held_balance->cents)->toBe(0)->and($from->available_balance->cents)->toBe(1_400_000);

    $decision = Decision::where('decidable_id', $b->id)->first();
    expect($decision->decision)->toBe('rejected')->and($decision->reason_code)->toBe('unusual_activity')->and($decision->decided_by)->toBe($admin->id);
});

it('requires a valid reason to reject or hold, and can release a hold', function () {
    $admin = makeUser('admin');
    $from = makeAccount(null, 2_000_000);
    $to = makeAccount(null, 0, 'GOLDEN-SAVINGS');
    $tx = transfer($from, $to, 600_000);

    expect(refused(fn () => service()->reject($tx, $admin, 'unusual_activity', '  ')))->toBe('reason_required');
    expect(refused(fn () => service()->hold($tx, $admin, 'not_a_code', 'x')))->toBe('reason_required');

    $held = service()->hold($tx, $admin, 'verification_required', 'We could not reach you.', now()->addDays(2));
    $decision = Decision::where('decision', 'held')->first();
    expect($held->status)->toBe(TransactionStatus::Held)
        ->and($decision->hold_until)->not->toBeNull()
        ->and($from->refresh()->held_balance->cents)->toBe(600_000);

    expect(service()->release($held, $admin)->status)->toBe(TransactionStatus::Pending);
    expect(service()->approve($tx, $admin)->status)->toBe(TransactionStatus::Completed);
});

it('does not let anyone approve what they started', function () {
    $admin = makeUser('admin');
    $from = makeAccount(null, 2_000_000);
    $to = makeAccount(null, 0, 'GOLDEN-SAVINGS');
    $tx = transfer($from, $to, 600_000, by: $admin->id);

    expect(refused(fn () => service()->approve($tx, $admin)))->toBe('self_approval');

    // and not while acting as a member on behalf of an admin either
    $tx2 = service()->execute(new TransferRequest(type: TransactionType::Transfer, amount: Money::cents(600_000), idempotencyKey: 'imp', fromAccountId: $from->id, toAccountId: $to->id, initiatedBy: $from->users()->first()->id, impersonatorId: $admin->id));
    expect($tx2->impersonator_id)->toBe($admin->id)
        ->and(refused(fn () => service()->approve($tx2, $admin)))->toBe('self_approval');
});

it('needs two different admins above the dual-approval limit', function () {
    config(['bank.per_transaction_limit' => 9_000_000, 'bank.default_daily_limit' => 9_000_000]);
    $one = makeUser('admin');
    $two = makeUser('admin');
    $from = makeAccount(null, 5_000_000);
    $to = makeAccount(null, 0, 'GOLDEN-SAVINGS');
    $tx = transfer($from, $to, 3_000_000);

    $after = service()->approve($tx, $one);
    expect($after->status)->toBe(TransactionStatus::Pending)->and($to->refresh()->balance->cents)->toBe(0);

    $again = service()->approve($tx, $one); // the same admin twice does not count twice
    expect($again->status)->toBe(TransactionStatus::Pending);

    $done = service()->approve($tx, $two);
    expect($done->status)->toBe(TransactionStatus::Completed)->and($to->refresh()->balance->cents)->toBe(3_000_000);
});

it('always sends outgoing wires to review', function () {
    $account = makeAccount(null, 100_000);
    $tx = service()->execute(new TransferRequest(type: TransactionType::WireOut, amount: Money::cents(5_000), idempotencyKey: 'w1', fromAccountId: $account->id));

    expect($tx->status)->toBe(TransactionStatus::Pending)->and($account->refresh()->held_balance->cents)->toBe(5_000);
});

it('does not decide a transaction twice', function () {
    $admin = makeUser('admin');
    $from = makeAccount(null, 2_000_000);
    $to = makeAccount(null, 0, 'GOLDEN-SAVINGS');
    $tx = transfer($from, $to, 600_000);
    service()->approve($tx, $admin);

    expect(refused(fn () => service()->approve($tx, $admin)))->toBe('invalid_state');
    expect(refused(fn () => service()->reject($tx, $admin, 'unusual_activity', 'x')))->toBe('invalid_state');
});

it('reverses a completed transaction with a new transaction and refuses a second reversal', function () {
    $admin = makeUser('admin');
    $from = makeAccount(null, 100_000);
    $to = makeAccount(null, 0, 'GOLDEN-SAVINGS');
    $tx = transfer($from, $to, 40_000);

    $reversal = service()->reverse($tx, $admin, 'error_correction', 'Sent to the wrong account.');

    expect($reversal->type)->toBe(TransactionType::Reversal)
        ->and($reversal->related_transaction_id)->toBe($tx->id)
        ->and($tx->refresh()->status)->toBe(TransactionStatus::Reversed)
        ->and($from->refresh()->balance->cents)->toBe(100_000)->and($to->refresh()->balance->cents)->toBe(0);
    expect(refused(fn () => service()->reverse($tx, $admin, 'error_correction', 'again')))->toBe('invalid_state');
});

it('never lets a reversal overdraw the receiving account', function () {
    $admin = makeUser('admin');
    $from = makeAccount(null, 100_000);
    $to = makeAccount(null, 0, 'GOLDEN-SAVINGS');
    $tx = transfer($from, $to, 40_000);
    service()->execute(new TransferRequest(type: TransactionType::CardPurchase, amount: Money::cents(30_000), idempotencyKey: 'spent', fromAccountId: $to->id));

    expect(refused(fn () => service()->reverse($tx, $admin, 'error_correction', 'oops')))->toBe('insufficient_funds');
    expect($tx->refresh()->status)->toBe(TransactionStatus::Completed);
});

it('keeps ledger, decision, audit and transaction rows append-only', function () {
    $a = makeAccount(null, 10_000);
    $entry = LedgerEntry::first();
    $audit = AuditLog::first();

    expect(fn () => $entry->update(['amount' => 1]))->toThrow(LogicException::class);
    expect(fn () => $entry->delete())->toThrow(LogicException::class);
    expect(fn () => $audit->update(['action' => 'x']))->toThrow(LogicException::class);
    expect(fn () => $audit->delete())->toThrow(LogicException::class);
    expect(fn () => Transaction::first()->delete())->toThrow(LogicException::class);
});

it('stores money columns as integer cents and exposes Money objects', function () {
    $a = makeAccount(null, 123_456);

    expect(DB::table('accounts')->where('id', $a->id)->value('balance'))->toBe(123_456)
        ->and($a->balance)->toBeInstanceOf(Money::class)
        ->and($a->balance->format())->toBe('$1,234.56');
});

it('reports a balance that disagrees with the ledger', function () {
    $a = makeAccount(null, 10_000);
    $this->artisan('ledger:verify')->assertSuccessful();

    DB::table('accounts')->where('id', $a->id)->update(['balance' => 99_999]);
    $this->artisan('ledger:verify')->assertFailed();
});

it('keeps balances equal to the ledger after a mix of operations', function () {
    $admin = makeUser('admin');
    $user = makeUser();
    $savings = makeAccount($user, 900_000, 'GOLDEN-SAVINGS');
    $checking = makeAccount($user, 50_000, overdraftId: $savings->id);

    transfer($checking, $savings, 20_000);
    service()->execute(new TransferRequest(type: TransactionType::CardPurchase, amount: Money::cents(60_000), idempotencyKey: 'mix1', fromAccountId: $checking->id));
    $big = transfer($savings, $checking, 700_000);
    service()->hold($big, $admin, 'unusual_activity', 'Reviewing', now()->addDay());
    $this->artisan('ledger:verify')->assertSuccessful();
    service()->release($big, $admin);
    service()->approve($big, $admin);
    $this->artisan('ledger:verify')->assertSuccessful();
});
