<?php

use App\Models\AccountRestriction;
use App\Models\AuditLog;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    seedReference();
    config(['bank.default_daily_limit' => 50_000_000, 'bank.per_transaction_limit' => 50_000_000]);
});

/** A member with a large checking balance who has asked to move $amount, waiting in the queue. */
function pendingTransfer(int $amountCents, int $balance = 10_000_000): array
{
    [$member, $checking, $savings] = verifiedMember();
    deposit($checking, $balance);
    test()->actingAs($member)->post('/transfers', ['token' => (string) Str::ulid(), 'from_account_id' => $checking->id, 'to_account_id' => $savings->id, 'amount' => number_format($amountCents / 100, 2, '.', '')]);

    return [$member, $checking, $savings, Transaction::latest('id')->first()];
}

it('shows queue items to staff and approves one so it posts', function () {
    [, $checking, $savings, $tx] = pendingTransfer(600_000);
    $manager = makeStaff('Manager');

    $this->actingAs($manager)->get('/admin/queue')->assertOk()->assertSee('$6,000.00');
    $this->actingAs($manager)->post("/admin/queue/{$tx->reference}/approve", ['note' => 'Checked'])->assertRedirect();

    expect($tx->refresh()->status->value)->toBe('completed')->and($savings->refresh()->balance->cents)->toBe(600_000);
    expect(AuditLog::where('action', 'like', 'transaction.%')->where('actor_id', $manager->id)->exists())->toBeTrue();
});

it('shows the member the reason when staff hold a transfer', function () {
    [$member, , , $tx] = pendingTransfer(600_000);
    $this->actingAs(makeStaff('Compliance'))->post("/admin/queue/{$tx->reference}/hold", ['reason_code' => 'verification_required', 'reason_text' => 'We are confirming this with you by phone.', 'until' => now()->addDays(2)->toDateString()])->assertRedirect();

    $this->actingAs($member)->get(route('transactions.show', $tx->reference))->assertSee('confirming this with you by phone')->assertSee('Contest this decision');
    $this->actingAs($member)->get('/dashboard')->assertSee('On hold');
});

it('requires a reason and text to hold or reject', function () {
    [, , , $tx] = pendingTransfer(600_000);
    $manager = makeStaff('Manager');

    $this->actingAs($manager)->post("/admin/queue/{$tx->reference}/reject", ['reason_code' => 'suspected_fraud'])->assertSessionHasErrors('reason_text');
    $this->actingAs($manager)->post("/admin/queue/{$tx->reference}/hold", ['reason_text' => 'x'])->assertSessionHasErrors('reason_code');
    expect($tx->refresh()->status->value)->toBe('pending');
});

it('releases the reserved money when staff reject', function () {
    [$member, $checking, , $tx] = pendingTransfer(600_000, 1_000_000);
    expect($checking->refresh()->available_balance->cents)->toBe(400_000);

    $this->actingAs(makeStaff('Manager'))->post("/admin/queue/{$tx->reference}/reject", ['reason_code' => 'unusual_activity', 'reason_text' => 'This does not match how you usually use the account.']);

    expect($checking->refresh()->available_balance->cents)->toBe(1_000_000)->and($tx->refresh()->status->value)->toBe('rejected');
    $this->actingAs($member)->get(route('transactions.show', $tx->reference))->assertSee('does not match how you usually');
});

it('needs two different approvers for a large amount', function () {
    [, , $savings, $tx] = pendingTransfer(2_600_000, 5_000_000);
    $one = makeStaff('Manager', name: 'Daniel Brooks');
    $two = makeStaff('Compliance', name: 'Rachel Kim');

    $this->actingAs($one)->post("/admin/queue/{$tx->reference}/approve")->assertSessionHas('status');
    expect($tx->refresh()->status->value)->toBe('pending');
    $this->actingAs($one)->post("/admin/queue/{$tx->reference}/approve");
    expect($tx->refresh()->status->value)->toBe('pending');
    $this->actingAs($two)->post("/admin/queue/{$tx->reference}/approve");
    expect($tx->refresh()->status->value)->toBe('completed')->and($savings->refresh()->balance->cents)->toBe(2_600_000);
});

it('lets a Teller decide small amounts but not large ones', function () {
    [, , , $big] = pendingTransfer(600_000);
    $teller = makeStaff('Teller');

    $this->actingAs($teller)->post("/admin/queue/{$big->reference}/approve")->assertForbidden();
    $this->actingAs($teller)->get("/admin/queue/{$big->reference}")->assertOk()->assertSee('can view this transaction but not decide');
});

it('locks an account so a debit fails, records it and tells the member', function () {
    [$member, $checking, $savings] = verifiedMember();
    deposit($checking, 100_000);
    $manager = makeStaff('Manager');

    $this->actingAs($manager)->post("/admin/members/{$member->id}/locks", ['target' => $checking->id, 'type' => 'debit_block', 'reason_code' => 'account_review', 'reason_text' => 'We are reviewing recent activity.'])->assertRedirect();

    $this->actingAs($member)->post('/transfers', ['token' => (string) Str::ulid(), 'from_account_id' => $checking->id, 'to_account_id' => $savings->id, 'amount' => '10.00'])->assertSessionHasErrors('amount');
    expect($checking->refresh()->balance->cents)->toBe(100_000);
    $this->actingAs($member)->get('/dashboard')->assertOk()->assertSee('has a restriction')->assertSee('Money cannot be sent out');
    expect(AuditLog::where('action', 'restriction.created')->count())->toBe(1);
});

it('needs a note to unlock and then lets money move again', function () {
    [$member, $checking, $savings] = verifiedMember();
    deposit($checking, 100_000);
    $manager = makeStaff('Manager');
    $this->actingAs($manager)->post("/admin/members/{$member->id}/locks", ['target' => 'member', 'type' => 'full_lock', 'reason_code' => 'account_review', 'reason_text' => 'Review.']);
    $lock = AccountRestriction::firstOrFail();

    $this->actingAs($manager)->post("/admin/locks/{$lock->id}/unlock", ['note' => ''])->assertSessionHasErrors('note');
    $this->actingAs($manager)->post("/admin/locks/{$lock->id}/unlock", ['note' => 'Identity confirmed by phone.'])->assertRedirect();
    expect($lock->refresh()->unlocked_by)->toBe($manager->id);

    $this->actingAs($member)->post('/transfers', ['token' => (string) Str::ulid(), 'from_account_id' => $checking->id, 'to_account_id' => $savings->id, 'amount' => '10.00']);
    expect($savings->refresh()->balance->cents)->toBe(1_000);
    expect(AuditLog::whereIn('action', ['restriction.created', 'restriction.released'])->count())->toBe(2);
});

it('posts a credit adjustment the member sees by its description', function () {
    [$member, $checking] = verifiedMember();
    $this->actingAs(makeStaff('Manager'))->post("/admin/members/{$member->id}/adjustments", ['token' => (string) Str::ulid(), 'account_id' => $checking->id, 'direction' => 'credit', 'amount' => '75.00', 'description' => 'Goodwill credit for a branch error', 'reason_code' => 'goodwill', 'note' => 'Approved by branch lead'])->assertRedirect();

    expect($checking->refresh()->balance->cents)->toBe(7_500);
    $this->actingAs($member)->get('/activity')->assertSee('Goodwill credit for a branch error');
    $this->artisan('ledger:verify')->assertSuccessful();
});

it('refuses a debit adjustment that would take an account below zero', function () {
    [$member, $checking] = verifiedMember();
    deposit($checking, 1_000);
    $this->actingAs(makeStaff('Manager'))->post("/admin/members/{$member->id}/adjustments", ['token' => (string) Str::ulid(), 'account_id' => $checking->id, 'direction' => 'debit', 'amount' => '50.00', 'description' => 'Correction', 'reason_code' => 'error_correction', 'note' => 'n'])->assertSessionHasErrors('amount');
    expect($checking->refresh()->balance->cents)->toBe(1_000);
});

it('caps what a Manager can adjust and lets a Super Admin go further with a second approver', function () {
    [$member, $checking] = verifiedMember();
    $payload = fn () => ['token' => (string) Str::ulid(), 'account_id' => $checking->id, 'direction' => 'credit', 'amount' => '6000.00', 'description' => 'Correction', 'reason_code' => 'error_correction', 'note' => 'n'];

    $this->actingAs(makeStaff('Manager'))->post("/admin/members/{$member->id}/adjustments", $payload())->assertForbidden();

    $super = makeStaff('Super Admin', name: 'Alexandra Reyes');
    $this->actingAs($super)->post("/admin/members/{$member->id}/adjustments", $payload())->assertRedirect();
    $tx = Transaction::latest('id')->first();
    expect($tx->status->value)->toBe('pending')->and($checking->refresh()->balance->cents)->toBe(0);

    $this->actingAs($super)->post("/admin/queue/{$tx->reference}/approve");
    expect($tx->refresh()->status->value)->toBe('pending');

    $this->actingAs(makeStaff('Compliance', name: 'Rachel Kim'))->post("/admin/queue/{$tx->reference}/approve");
    expect($tx->refresh()->status->value)->toBe('completed')->and($checking->refresh()->balance->cents)->toBe(600_000);
});

it('shows audit entries with filters and has no way to change them', function () {
    [$member, $checking] = verifiedMember();
    $manager = makeStaff('Manager', name: 'Daniel Brooks');
    $this->actingAs($manager)->post("/admin/members/{$member->id}/locks", ['target' => 'member', 'type' => 'debit_block', 'reason_code' => 'account_review', 'reason_text' => 'Review.']);

    $this->actingAs($manager)->get('/admin/audit?action=restriction')->assertOk()->assertSee('restriction.created')->assertSee('Daniel Brooks');
    $this->actingAs($manager)->get('/admin/audit?action=nothing-like-this')->assertSee('No entries match');
    $this->actingAs($manager)->get('/admin/audit?actor=Brooks&from='.now()->toDateString())->assertSee('restriction.created');

    $writes = collect(app('router')->getRoutes()->getRoutes())->filter(fn ($r) => str_contains($r->uri(), 'audit') && array_diff($r->methods(), ['GET', 'HEAD']));
    expect($writes)->toBeEmpty();
    expect(fn () => AuditLog::first()->delete())->toThrow(LogicException::class);
});
