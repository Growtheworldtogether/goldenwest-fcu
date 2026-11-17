<?php

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\AccountHolder;
use App\Models\AccountRestriction;
use App\Models\AuditLog;
use App\Models\Beneficiary;
use App\Models\Card;
use App\Models\JointRequest;
use App\Models\User;
use App\Services\BeneficiaryService;
use App\Services\JointService;
use App\Services\Ledger\TransferException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    seedReference();
    config(['bank.default_daily_limit' => 50_000_000, 'bank.per_transaction_limit' => 50_000_000]);
});

/** Two verified members. Maria has $500 in checking. */
function pair(): array
{
    [$a, $ac, $as] = verifiedMember('Maria Alvarez');
    [$b, $bc, $bs] = verifiedMember('Jon Alvarez');
    $a->forceFill(['kyc_status' => 'verified'])->save();
    $b->forceFill(['kyc_status' => 'verified'])->save();
    deposit($ac, 50_000);

    return [$a->refresh(), $b->refresh(), $ac->refresh(), $bc->refresh()];
}

/** Maria invites Jon and staff approve. */
function makeJoint(User $a, User $b, Account $account): JointRequest
{
    $svc = app(JointService::class);
    $req = $svc->invite($a, $account, $b->email);
    $svc->respondToInvite($req, $b, true);

    return $svc->decide($req, makeStaff('Manager'), true);
}

function ben(string $name, string $tier, int $share, string $rel = 'child', string $dob = '2010-05-04'): array
{
    return ['name' => $name, 'relationship' => $rel, 'dob' => $dob, 'tier' => $tier, 'share_percent' => $share];
}

it('saves beneficiaries with both tiers totalling 100% and keeps the old set as history', function () {
    [$a, , $ac] = pair();
    $svc = app(BeneficiaryService::class);
    $svc->replace($ac, $a, [ben('Lena Alvarez', 'primary', 60), ben('Tomas Alvarez', 'primary', 40), ben('Rosa Alvarez', 'contingent', 100, 'parent', '1960-01-01')]);
    expect($svc->forAccount($ac))->toHaveCount(3)->and($svc->state($ac))->toBe('complete');

    $svc->replace($ac, $a, [ben('Lena Alvarez', 'primary', 100)]);
    expect($svc->forAccount($ac))->toHaveCount(1)
        ->and(Beneficiary::where('account_id', $ac->id)->where('status', 'removed')->count())->toBe(3)
        ->and(AuditLog::where('action', 'beneficiaries.updated')->count())->toBe(2);

    $svc->replace($ac, $a, []);
    expect($svc->state($ac))->toBe('none');
});

it('refuses shares that do not total 100%, a missing primary, bad dates and duplicates', function () {
    [$a, , $ac] = pair();
    $svc = app(BeneficiaryService::class);
    $try = fn (array $rows) => (function () use ($svc, $ac, $a, $rows) {
        try {
            $svc->replace($ac, $a, $rows);
        } catch (ValidationException $e) {
            return collect($e->errors())->flatten()->implode(' ');
        }

        return 'saved';
    })();

    expect($try([ben('A One', 'primary', 60), ben('B Two', 'primary', 30)]))->toContain('90%')
        ->and($try([ben('A One', 'primary', 100), ben('B Two', 'contingent', 50)]))->toContain('Contingent shares add up to 50%')
        ->and($try([ben('A One', 'contingent', 100)]))->toContain('at least one primary')
        ->and($try([ben('A One', 'primary', 100, 'child', now()->addDay()->toDateString())]))->toContain('in the past')
        ->and($try([ben('A One', 'primary', 50), ben('a one', 'primary', 50)]))->toContain('listed twice')
        ->and($try([ben('A One', 'primary', 100, 'cousin')]))->toContain('relationship')
        ->and($try([ben('A One', 'primary', 0)]))->toContain('at least 1%');
    expect($svc->forAccount($ac))->toHaveCount(0);
});

it('serves the beneficiary pages and saves through the form', function () {
    [$a, , $ac] = pair();
    $this->actingAs($a)->get('/beneficiaries')->assertOk()->assertSee('Add beneficiaries');
    $this->actingAs($a)->get("/beneficiaries/{$ac->id}")->assertOk()->assertSee('Save beneficiaries');
    $this->actingAs($a)->put("/beneficiaries/{$ac->id}", ['rows' => [ben('Lena Alvarez', 'primary', 100)]])->assertRedirect('/beneficiaries')->assertSessionHas('toast.type', 'ok');
    $this->actingAs($a)->get('/beneficiaries')->assertSee('Lena Alvarez')->assertSee('100%');
    $this->actingAs($a)->put("/beneficiaries/{$ac->id}", ['rows' => [ben('X Y', 'primary', 70)]])->assertSessionHasErrors('rows')->assertRedirect();
});

it('keeps another member out of an account\'s beneficiaries', function () {
    [$a, $b, $ac] = pair();
    $this->actingAs($b)->get("/beneficiaries/{$ac->id}")->assertForbidden();
    $this->actingAs($b)->put("/beneficiaries/{$ac->id}", ['rows' => [ben('Z Z', 'primary', 100)]])->assertForbidden();
});

it('walks a joint invitation from invite to accepted to approved, and both holders see the account', function () {
    [$a, $b, $ac] = pair();
    $this->actingAs($a)->post('/joint/invite', ['account_id' => $ac->id, 'email' => strtoupper($b->email), 'message' => 'Our household account'])->assertSessionHas('toast.type', 'ok');
    $req = JointRequest::first();
    expect($req->status)->toBe('invited');
    $this->actingAs($b)->get('/dashboard')->assertSee('invited you to share');
    $this->actingAs($b)->get('/joint')->assertSee('Our household account');
    expect($b->accounts()->where('accounts.id', $ac->id)->exists())->toBeFalse();

    $this->actingAs($b)->post("/joint/{$req->reference}/respond", ['action' => 'accept'])->assertSessionHas('toast.type', 'ok');
    expect($req->refresh()->status)->toBe('review')->and($b->accounts()->where('accounts.id', $ac->id)->exists())->toBeFalse();

    $manager = makeStaff('Manager');
    $this->actingAs(makeStaff('Teller'))->post("/admin/joint/{$req->reference}/decide", ['decision' => 'approve'])->assertForbidden();
    $this->actingAs($manager)->post("/admin/joint/{$req->reference}/decide", ['decision' => 'approve'])->assertSessionHas('toast.type', 'ok');

    expect($req->refresh()->status)->toBe('approved')->and($req->decisions()->first()->decision)->toBe('approved')
        ->and($b->accounts()->where('accounts.id', $ac->id)->exists())->toBeTrue()
        ->and(app(JointService::class)->holderRow($ac, $b)->role)->toBe('joint');
    $this->actingAs($b)->get("/accounts/{$ac->id}")->assertOk()->assertSee('Joint holder');
    $this->actingAs($a)->get("/accounts/{$ac->id}")->assertOk()->assertSee('Jon Alvarez');

    // Jon can spend from it.
    $tx = transfer($ac, $b->accounts()->where('accounts.id', '!=', $ac->id)->first(), 1_000, null, TransactionType::Transfer, $b->id);
    expect($tx->status->value)->toBe('completed');
});

it('refuses invitations to unknown, unverified, existing or self, and a second open invite', function () {
    [$a, $b, $ac] = pair();
    $svc = app(JointService::class);
    $msg = fn (callable $f) => (function () use ($f) {
        try {
            $f();
        } catch (InvalidArgumentException $e) {
            return $e->getMessage();
        }

        return 'ok';
    })();

    expect($msg(fn () => $svc->invite($a, $ac, 'nobody@example.com')))->toContain('could not find a verified member');
    $b->forceFill(['kyc_status' => 'pending'])->save();
    expect($msg(fn () => $svc->invite($a, $ac, $b->email)))->toContain('could not find a verified member');
    $b->forceFill(['kyc_status' => 'verified'])->save();
    expect($msg(fn () => $svc->invite($a, $ac, $a->email)))->toContain('already a holder');

    $svc->invite($a, $ac, $b->email);
    expect($msg(fn () => $svc->invite($a, $ac, $b->email)))->toContain('already an invitation open');
    $a->forceFill(['kyc_status' => 'pending'])->save();
    $JointRequest = JointRequest::first();
    $JointRequest->update(['status' => 'cancelled']);
    expect($msg(fn () => $svc->invite($a->refresh(), $ac, $b->email)))->toContain('Verify your identity');
    $a->forceFill(['kyc_status' => 'verified'])->save();
    [$c] = verifiedMember('Third Person');
    $c->forceFill(['kyc_status' => 'verified'])->save();
    makeJoint($a->refresh(), $b->refresh(), $ac);
    config(['bank.max_joint_holders' => 2]);
    expect($msg(fn () => $svc->invite($a, $ac, $c->email)))->toContain('most holders');
    expect($msg(fn () => $svc->invite($c, $ac, $a->email)))->toContain('not a holder');
});

it('lets the invitee decline and a holder cancel, and shows staff rejections to both', function () {
    [$a, $b, $ac] = pair();
    $svc = app(JointService::class);
    $r1 = $svc->invite($a, $ac, $b->email);
    $this->actingAs($b)->post("/joint/{$r1->reference}/respond", ['action' => 'decline'])->assertSessionHas('toast.type', 'ok');
    expect($r1->refresh()->status)->toBe('declined');

    $r2 = $svc->invite($a, $ac, $b->email);
    $this->actingAs($a)->post("/joint/{$r2->reference}/cancel")->assertSessionHas('toast.type', 'ok');
    expect($r2->refresh()->status)->toBe('cancelled');

    $r3 = $svc->invite($a, $ac, $b->email);
    $svc->respondToInvite($r3, $b, true);
    $manager = makeStaff('Manager');
    $this->actingAs($manager)->post("/admin/joint/{$r3->reference}/decide", ['decision' => 'reject'])->assertSessionHas('toast.type', 'bad'); // reason needed
    $this->actingAs($manager)->post("/admin/joint/{$r3->reference}/decide", ['decision' => 'reject', 'reason_code' => 'insufficient_documentation', 'reason_text' => 'We need a proof of relationship.'])->assertSessionHas('toast.type', 'ok');
    expect($r3->refresh()->status)->toBe('rejected')->and($b->accounts()->where('accounts.id', $ac->id)->exists())->toBeFalse();
    $this->actingAs($a)->get('/joint')->assertSee('We need a proof of relationship.');
    $this->actingAs($manager)->post("/admin/joint/{$r3->reference}/decide", ['decision' => 'approve'])->assertSessionHas('toast.type', 'bad'); // already handled
});

it('keeps other people from answering someone else\'s invitation', function () {
    [$a, $b, $ac] = pair();
    [$c] = verifiedMember('Carol Diaz');
    $req = app(JointService::class)->invite($a, $ac, $b->email);
    $this->actingAs($c)->post("/joint/{$req->reference}/respond", ['action' => 'accept'])->assertNotFound();
    $this->actingAs($c)->post("/joint/{$req->reference}/cancel")->assertNotFound();
    expect($req->refresh()->status)->toBe('invited');
});

it('stops a staff member deciding on a request they are part of', function () {
    [$a, $b, $ac] = pair();
    $svc = app(JointService::class);
    $req = $svc->invite($a, $ac, $b->email);
    $svc->respondToInvite($req, $b, true);
    $staff = makeStaff('Manager');
    $staff->forceFill(['status' => 'active'])->save();
    $req->update(['requested_by' => $staff->id]);
    expect(fn () => $svc->decide($req, $staff, true))->toThrow(InvalidArgumentException::class, 'involves you');
});

it('applies a lock to both holders', function () {
    [$a, $b, $ac, $bc] = pair();
    makeJoint($a, $b, $ac);
    $manager = makeStaff('Manager');

    // An account lock stops Jon as well as Maria.
    $lock = app(App\Services\RestrictionService::class)->lock($manager, $ac, null, AccountRestriction::FULL_LOCK, 'account_review', 'Under review');
    $reason = fn () => (function () use ($ac, $bc, $b) {
        try {
            transfer($ac, $bc, 500, null, TransactionType::Transfer, $b->id);
        } catch (TransferException $e) {
            return $e->reason;
        }

        return 'ok';
    })();
    expect($reason())->toBe('account_locked');
    $this->actingAs($b)->get('/dashboard')->assertSee('has a restriction');
    app(App\Services\RestrictionService::class)->unlock($manager, $lock, 'Cleared');
    expect($reason())->toBe('ok');

    // A lock on Maria alone also stops the joint account for Jon.
    app(App\Services\RestrictionService::class)->lock($manager, null, $a, AccountRestriction::FULL_LOCK, 'account_review', 'Member lock');
    expect($reason())->toBe('account_locked');
});

it('removes a holder only with their consent, or with staff and a reason', function () {
    [$a, $b, $ac] = pair();
    makeJoint($a, $b, $ac);

    // Maria asks. Jon declines: nothing changes.
    $this->actingAs($a)->post('/joint/removal', ['account_id' => $ac->id, 'user_id' => $b->id])->assertSessionHas('toast.type', 'ok');
    $req = JointRequest::where('type', 'removal')->first();
    $this->actingAs($a)->post('/joint/removal', ['account_id' => $ac->id, 'user_id' => $b->id])->assertSessionHas('toast.type', 'bad'); // one at a time
    $this->actingAs($b)->get('/dashboard')->assertSee('asked to take you off');
    $this->actingAs($a)->post("/joint/{$req->reference}/consent", ['action' => 'agree'])->assertNotFound(); // not the holder asked
    $this->actingAs($b)->post("/joint/{$req->reference}/consent", ['action' => 'decline']);
    expect($req->refresh()->status)->toBe('declined')->and($b->accounts()->where('accounts.id', $ac->id)->exists())->toBeTrue();

    // Staff step in, with a reason.
    $manager = makeStaff('Manager');
    $this->actingAs($manager)->post("/admin/members/{$b->id}/joint/remove", ['account_id' => $ac->id, 'reason_code' => 'suspected_fraud'])->assertSessionHasErrors('reason_text');
    $this->actingAs(makeStaff('Teller'))->post("/admin/members/{$b->id}/joint/remove", ['account_id' => $ac->id, 'reason_code' => 'suspected_fraud', 'reason_text' => 'x'])->assertForbidden();
    $this->actingAs($manager)->post("/admin/members/{$b->id}/joint/remove", ['account_id' => $ac->id, 'reason_code' => 'suspected_fraud', 'reason_text' => 'Court order received.'])->assertSessionHas('toast.type', 'ok');
    expect($b->accounts()->where('accounts.id', $ac->id)->exists())->toBeFalse()
        ->and(AuditLog::where('action', 'joint.removed_by_staff')->exists())->toBeTrue();
    $this->actingAs($b)->get("/accounts/{$ac->id}")->assertForbidden();
});

it('removes a holder who agrees, and lets a holder leave', function () {
    [$a, $b, $ac] = pair();
    makeJoint($a, $b, $ac);
    $svc = app(JointService::class);

    $req = $svc->requestRemoval($a, $ac, $b);
    $this->actingAs($b)->post("/joint/{$req->reference}/consent", ['action' => 'agree'])->assertSessionHas('toast.type', 'ok');
    expect($req->refresh()->status)->toBe('done')->and($svc->holderRow($ac, $b))->toBeNull()->and($svc->holderRow($ac, $a))->not->toBeNull();

    makeJoint($a, $b, $ac);
    $this->actingAs($b)->post('/joint/leave', ['account_id' => $ac->id])->assertSessionHas('toast.type', 'ok');
    expect($svc->holderRow($ac, $b))->toBeNull();

    // Nobody can leave an account and leave it with no holder.
    $this->actingAs($a)->post('/joint/leave', ['account_id' => $ac->id])->assertSessionHas('toast.type', 'bad');
    expect($svc->holderRow($ac, $a))->not->toBeNull();
});

it('makes the remaining holder the primary when the primary leaves', function () {
    [$a, $b, $ac] = pair();
    makeJoint($a, $b, $ac);
    app(JointService::class)->leave($a, $ac);
    expect(app(JointService::class)->holderRow($ac, $b)->role)->toBe('primary');
});

it('cancels a removed holder\'s cards and scheduled bills on the account', function () {
    [$a, $b, $ac] = pair();
    makeJoint($a, $b, $ac);
    $card = Card::create(['user_id' => $b->id, 'account_id' => $ac->id, 'status' => 'active', 'last4' => '1234', 'requested_at' => now()]);
    app(JointService::class)->leave($b, $ac);
    expect($card->refresh()->status)->toBe('cancelled');
});

it('marks a member deceased: sole accounts lock, joint accounts stay open, sign-in stops', function () {
    [$a, $b, $ac, $bc] = pair();
    makeJoint($a, $b, $ac);
    $maria = $a->accounts()->where('accounts.id', '!=', $ac->id)->first(); // Maria's savings, sole
    $manager = makeStaff('Manager');

    $this->actingAs(makeStaff('Teller'))->post("/admin/members/{$a->id}/deceased", ['note' => 'Certificate'])->assertForbidden();
    $this->actingAs($manager)->post("/admin/members/{$a->id}/deceased", [])->assertSessionHasErrors('note');
    $this->actingAs($manager)->post("/admin/members/{$a->id}/deceased", ['note' => 'Death certificate received 11/16.'])->assertSessionHas('toast.type', 'ok');

    expect($a->refresh()->status)->toBe('deceased')->and($a->deceased_at)->not->toBeNull()
        ->and(AccountRestriction::active()->where('account_id', $maria->id)->where('reason_code', 'deceased_member')->exists())->toBeTrue()
        ->and(app(JointService::class)->holderRow($ac, $a))->toBeNull()
        ->and(app(JointService::class)->holderRow($ac, $b)->role)->toBe('primary')
        ->and(AccountRestriction::active()->where('account_id', $ac->id)->exists())->toBeFalse();
    // The survivor still uses the joint account.
    expect(transfer($ac, $bc, 500, null, TransactionType::Transfer, $b->id)->status->value)->toBe('completed');

    auth()->logout();
    $this->post('/login', ['email' => $a->email, 'password' => 'a-long-test-password-1'])->assertSessionHasErrors();
    $this->assertGuest();
    $this->actingAs($manager)->post("/admin/members/{$a->id}/deceased", ['note' => 'again'])->assertSessionHas('toast.type', 'bad');
});

it('still lets a normal member sign in', function () {
    [$a] = pair();
    $this->post('/login', ['email' => $a->email, 'password' => 'a-long-test-password-1'])->assertSessionHasNoErrors();
    $this->assertAuthenticatedAs($a);
});

it('shows the staff joint page and the Member 360 panel', function () {
    [$a, $b, $ac] = pair();
    $svc = app(JointService::class);
    $req = $svc->invite($a, $ac, $b->email);
    $svc->respondToInvite($req, $b, true);
    $manager = makeStaff('Manager');
    $this->actingAs($manager)->get('/admin/joint')->assertOk()->assertSee('Maria Alvarez')->assertSee('Approve');
    $this->actingAs($manager)->get('/admin/joint?tab=consent')->assertOk();
    $this->actingAs($manager)->get('/admin/joint?tab=all')->assertOk();
    $this->actingAs(makeStaff('Teller'))->get('/admin/joint')->assertForbidden();
    $svc->decide($req, $manager, true);
    $this->actingAs($manager)->get("/admin/members/{$a->id}")->assertOk()->assertSee('Holders and beneficiaries')->assertSee('Jon Alvarez')->assertSee('Mark as deceased');
});

it('shows the holders panel on the account page', function () {
    [$a, , $ac] = pair();
    $this->actingAs($a)->get("/accounts/{$ac->id}")->assertOk()->assertSee('Primary holder')->assertSee('Add a joint holder')->assertSee('None named');
});

it('lets up to the configured number of people share an account', function () {
    [$a, $b, $ac] = pair();
    $people = [verifiedMember('Third Person')[0], verifiedMember('Fourth Person')[0], verifiedMember('Fifth Person')[0]];
    foreach ($people as $p) {
        $p->forceFill(['kyc_status' => 'verified'])->save();
    }
    $svc = app(JointService::class);
    makeJoint($a, $b, $ac);
    foreach (array_slice($people, 0, 2) as $p) {
        makeJoint($a, $p->refresh(), $ac);
    }
    expect($svc->activeHolders($ac))->toHaveCount(4);
    expect(fn () => $svc->invite($a, $ac, $people[2]->email))->toThrow(InvalidArgumentException::class, '(4)');

    // Everyone sees the account, and a lock on any one holder stops it for all.
    foreach ([$b, ...array_slice($people, 0, 2)] as $p) {
        expect($p->accounts()->where('accounts.id', $ac->id)->exists())->toBeTrue();
    }
    app(App\Services\RestrictionService::class)->lock(makeStaff('Manager'), null, $people[0], AccountRestriction::FULL_LOCK, 'account_review', 'Review');
    expect(fn () => transfer($ac, $b->accounts()->where('accounts.id', '!=', $ac->id)->first(), 100, null, TransactionType::Transfer, $b->id))->toThrow(TransferException::class);
});

it('removes one of several holders with only their own consent, and promotes the longest-standing holder if the primary leaves', function () {
    [$a, $b, $ac] = pair();
    [$c] = verifiedMember('Third Person');
    $c->forceFill(['kyc_status' => 'verified'])->save();
    $svc = app(JointService::class);
    makeJoint($a, $b, $ac);
    $this->travel(1)->days();
    makeJoint($a, $c->refresh(), $ac);

    $req = $svc->requestRemoval($a, $ac, $c);
    $svc->respondToRemoval($req, $c, true);
    expect($svc->activeHolders($ac))->toHaveCount(2)->and($svc->holderRow($ac, $b))->not->toBeNull();

    makeJoint($a, $c, $ac);
    $svc->leave($a, $ac);
    expect($svc->holderRow($ac, $b)->role)->toBe('primary')->and($svc->holderRow($ac, $c)->role)->toBe('joint');
});

it('moves a removed holder\'s loan autopay and open application to an account they still hold', function () {
    [$a, $b, $ac, $bc] = pair();
    makeJoint($a, $b, $ac);
    App\Services\Features::set($b, 'loans', true, makeStaff('Manager'));
    $loan = makeLoan($b->refresh(), $ac);
    app(App\Services\Loans\LoanService::class)->setAutopay($loan, $b, $ac->id);
    $open = applyFor($b, $ac, 500_000, 12);
    expect($loan->refresh()->autopay_account_id)->toBe($ac->id);

    app(JointService::class)->leave($b, $ac);

    expect($loan->refresh()->autopay_account_id)->toBe($bc->id)
        ->and($open->refresh()->deposit_account_id)->toBe($bc->id)->and($open->status)->not->toBe('withdrawn')
        ->and(AuditLog::where('action', 'loan.autopay_moved')->where('subject_id', $loan->id)->exists())->toBeTrue();
});

it('switches autopay off and withdraws the application when the removed holder has no other account', function () {
    [$a, $b, $ac, $bc] = pair();
    makeJoint($a, $b, $ac);
    App\Services\Features::set($b, 'loans', true, makeStaff('Manager'));
    $loan = makeLoan($b->refresh(), $ac);
    app(App\Services\Loans\LoanService::class)->setAutopay($loan, $b, $ac->id);
    $open = applyFor($b, $ac, 500_000, 12);
    // Jon's own accounts are closed.
    Account::whereIn('id', $b->accounts()->where('accounts.id', '!=', $ac->id)->pluck('accounts.id'))->update(['status' => 'closed']);

    app(JointService::class)->leave($b, $ac);

    expect($loan->refresh()->autopay_account_id)->toBeNull()->and($open->refresh()->status)->toBe('withdrawn');
});

it('leaves the remaining holder\'s loan autopay alone', function () {
    [$a, $b, $ac] = pair();
    makeJoint($a, $b, $ac);
    App\Services\Features::set($a, 'loans', true, makeStaff('Manager'));
    $loan = makeLoan($a->refresh(), $ac);
    app(App\Services\Loans\LoanService::class)->setAutopay($loan, $a, $ac->id);
    app(JointService::class)->leave($b, $ac);
    expect($loan->refresh()->autopay_account_id)->toBe($ac->id);
});
