<?php

namespace App\Services;

use App\Models\Account;
use App\Notifications\MemberNotice;
use App\Models\AccountHolder;
use App\Models\BillPayment;
use App\Models\Card;
use App\Models\Decision;
use App\Models\Goal;
use App\Models\JointRequest;
use App\Models\User;
use App\Services\Ledger\TransferException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Joint holders. A holder invites an existing verified member, the member accepts, staff approve, and only then
 * does an account_holders row exist. Taking someone off needs their consent, or staff with a reason.
 */
class JointService
{
    public static function maxHolders(): int
    {
        return max(2, (int) config('bank.max_joint_holders'));
    }

    public function holderRow(Account $account, User|int $user): ?AccountHolder
    {
        return AccountHolder::where('account_id', $account->id)->where('user_id', $user instanceof User ? $user->id : $user)->where('status', 'active')->first();
    }

    public function activeHolders(Account $account)
    {
        return AccountHolder::with('user')->where('account_id', $account->id)->where('status', 'active')->orderByRaw("role = 'joint'")->get();
    }

    public function invite(User $by, Account $account, string $email, ?string $message = null): JointRequest
    {
        $this->assertManageable($by, $account);
        if ($by->kyc_status !== 'verified') {
            throw new InvalidArgumentException('Verify your identity before adding a joint holder.');
        }
        if ($this->activeHolders($account)->count() >= self::maxHolders()) {
            throw new InvalidArgumentException('This account already has the most holders we allow ('.self::maxHolders().').');
        }
        if (JointRequest::where('account_id', $account->id)->where('type', 'invite')->whereIn('status', ['invited', 'review'])->exists()) {
            throw new InvalidArgumentException('There is already an invitation open for this account. Cancel it first to invite someone else.');
        }
        $invitee = User::whereRaw('lower(email) = ?', [mb_strtolower(trim($email))])->first();
        if (! $invitee || $invitee->isAdmin() || $invitee->status !== 'active' || $invitee->kyc_status !== 'verified') {
            throw new InvalidArgumentException('We could not find a verified member with that email. They need a '.brand('name').' membership with a verified identity.');
        }
        if ($invitee->is($by) || $this->holderRow($account, $invitee)) {
            throw new InvalidArgumentException('That person is already a holder on this account.');
        }

        $req = JointRequest::create([
            'reference' => $this->reference(), 'type' => 'invite', 'account_id' => $account->id, 'requested_by' => $by->id,
            'subject_user_id' => $invitee->id, 'invitee_email' => $invitee->email, 'message' => $message ?: null, 'status' => 'invited',
        ]);
        Audit::record('joint.invited', $account, null, ['invitee' => $invitee->id], $by->id);
        MemberNotice::send($invitee, $by->first_name.' invited you to share an account', [$by->name.' invited you to be a joint holder on a '.brand('short_name').' account.', 'Sign in to read the invitation and accept or decline it. If you do not know this person, decline it.'], 'See the invitation', route('joint.index'));

        return $req;
    }

    public function cancelInvite(JointRequest $req, User $by): void
    {
        if ($req->type !== 'invite' || ! in_array($req->status, ['invited', 'review'], true)) {
            throw new InvalidArgumentException('This invitation can no longer be cancelled.');
        }
        if (! $this->holderRow($req->account, $by)) {
            throw new InvalidArgumentException('Only a holder of the account can cancel this.');
        }
        $req->update(['status' => 'cancelled']);
        Audit::record('joint.invite_cancelled', $req->account, null, null, $by->id);
    }

    /** The invited member accepts (it then waits for staff) or declines. */
    public function respondToInvite(JointRequest $req, User $invitee, bool $accept): JointRequest
    {
        if ($req->type !== 'invite' || $req->status !== 'invited' || $req->subject_user_id !== $invitee->id) {
            throw new InvalidArgumentException('This invitation is no longer open.');
        }
        $req->update(['status' => $accept ? 'review' : 'declined']);
        Audit::record($accept ? 'joint.accepted' : 'joint.declined', $req->account, null, null, $invitee->id);

        return $req->refresh();
    }

    /** Staff approve (the holder row is created) or reject with a reason. */
    public function decide(JointRequest $req, User $staff, bool $approve, ?string $reasonCode = null, ?string $reasonText = null): JointRequest
    {
        if (! $approve && ! $reasonText) {
            throw new InvalidArgumentException('Give a reason the members can read.');
        }

        $result = DB::transaction(function () use ($req, $staff, $approve, $reasonCode, $reasonText) {
            $req = JointRequest::lockForUpdate()->findOrFail($req->id);
            if ($req->type !== 'invite' || $req->status !== 'review') {
                throw new InvalidArgumentException('This request was already handled.');
            }
            if ($staff->id === $req->requested_by || $staff->id === $req->subject_user_id) {
                throw new InvalidArgumentException('You cannot decide on a request that involves you.');
            }
            $account = $req->account;
            $invitee = $req->subject;

            if ($approve) {
                if ($this->activeHolders($account)->count() >= self::maxHolders()) {
                    throw new InvalidArgumentException('The account already has the most holders we allow.');
                }
                if ($invitee->status !== 'active' || $invitee->kyc_status !== 'verified') {
                    throw new InvalidArgumentException('The invited member is no longer eligible.');
                }
                AccountHolder::updateOrCreate(['account_id' => $account->id, 'user_id' => $invitee->id], ['role' => 'joint', 'status' => 'active', 'added_at' => now(), 'removed_at' => null]);
            }
            $req->update(['status' => $approve ? 'approved' : 'rejected', 'decided_by' => $staff->id, 'decided_at' => now()]);
            Decision::create([
                'decidable_type' => $req->getMorphClass(), 'decidable_id' => $req->id, 'decision' => $approve ? 'approved' : 'rejected',
                'reason_code' => $approve ? null : $reasonCode, 'reason_text' => $approve ? null : $reasonText, 'decided_by' => $staff->id,
            ]);
            Audit::record($approve ? 'joint.approved' : 'joint.rejected', $account, null, ['invitee' => $invitee->id, 'reason' => $reasonCode], $staff->id);

            return $req->refresh();
        });
        $word = $approve ? 'approved' : 'declined';
        foreach ([$result->requester, $result->subject] as $who) {
            MemberNotice::send($who, 'Your joint account request was '.$word, ['We '.$word.' the request to add a joint holder to the account.', 'Sign in to see the details.'], 'Open joint accounts', route('joint.index'));
        }

        return $result;
    }

    /** A holder leaves: immediate, because they are the person whose consent is needed. Someone must stay. */
    public function leave(User $member, Account $account): void
    {
        $this->assertManageable($member, $account);
        $this->removeHolder($account, $member, $member, 'member_left');
    }

    /** One holder asks to take another off. The holder being removed must agree. */
    public function requestRemoval(User $by, Account $account, User $target): JointRequest
    {
        $this->assertManageable($by, $account);
        if ($by->is($target) || ! $this->holderRow($account, $target)) {
            throw new InvalidArgumentException('Choose another holder of this account.');
        }
        if (JointRequest::where('account_id', $account->id)->where('type', 'removal')->where('status', 'consent')->exists()) {
            throw new InvalidArgumentException('A removal request is already waiting for an answer.');
        }
        $req = JointRequest::create([
            'reference' => $this->reference(), 'type' => 'removal', 'account_id' => $account->id, 'requested_by' => $by->id,
            'subject_user_id' => $target->id, 'status' => 'consent',
        ]);
        Audit::record('joint.removal_requested', $account, null, ['holder' => $target->id], $by->id);
        MemberNotice::send($target, 'A joint account holder asked to remove you', ['Another holder on a joint account you share asked to remove you from it.', 'Sign in to answer. Nothing changes until you do.'], 'Answer the request', route('joint.index'));

        return $req;
    }

    public function respondToRemoval(JointRequest $req, User $target, bool $consent): JointRequest
    {
        if ($req->type !== 'removal' || $req->status !== 'consent' || $req->subject_user_id !== $target->id) {
            throw new InvalidArgumentException('This request is no longer open.');
        }
        DB::transaction(function () use ($req, $target, $consent) {
            if ($consent) {
                $this->removeHolder($req->account, $target, $target, 'consent');
            }
            $req->update(['status' => $consent ? 'done' : 'declined', 'decided_by' => $target->id, 'decided_at' => now()]);
            Audit::record($consent ? 'joint.removal_consented' : 'joint.removal_declined', $req->account, null, null, $target->id);
        });

        return $req->refresh();
    }

    public function cancelRemoval(JointRequest $req, User $by): void
    {
        if ($req->type !== 'removal' || $req->status !== 'consent' || $req->requested_by !== $by->id) {
            throw new InvalidArgumentException('This request can no longer be cancelled.');
        }
        $req->update(['status' => 'cancelled']);
        Audit::record('joint.removal_cancelled', $req->account, null, null, $by->id);
    }

    /** Staff take a holder off when they have not consented, with a reason. */
    public function adminRemove(Account $account, User $target, User $staff, string $reasonCode, string $reasonText): void
    {
        if (trim($reasonText) === '') {
            throw new InvalidArgumentException('A reason is required.');
        }
        if ($staff->is($target)) {
            throw new InvalidArgumentException('You cannot remove yourself.');
        }
        DB::transaction(function () use ($account, $target, $staff, $reasonCode, $reasonText) {
            $this->removeHolder($account, $target, $staff, 'staff');
            JointRequest::where('account_id', $account->id)->where('type', 'removal')->where('status', 'consent')->where('subject_user_id', $target->id)
                ->update(['status' => 'done', 'decided_by' => $staff->id, 'decided_at' => now()]);
            Audit::record('joint.removed_by_staff', $account, null, ['holder' => $target->id, 'reason' => $reasonCode, 'text' => $reasonText], $staff->id);
        });
    }

    /** Takes a holder off an account and tidies what only they could use. At least one holder always stays. */
    public function removeHolder(Account $account, User $target, User $actor, string $how): void
    {
        $moved = DB::transaction(function () use ($account, $target, $actor, $how) {
            $row = AccountHolder::where('account_id', $account->id)->where('user_id', $target->id)->where('status', 'active')->lockForUpdate()->first();
            if (! $row) {
                throw new InvalidArgumentException('That person is not a holder of this account.');
            }
            $others = AccountHolder::where('account_id', $account->id)->where('status', 'active')->where('user_id', '!=', $target->id)->orderBy('added_at')->orderBy('id')->get();
            if ($others->isEmpty()) {
                throw new InvalidArgumentException('An account needs at least one holder. To close it, contact Customer Care.');
            }

            // Their cards, savings goals and scheduled bills on this account stop with their access.
            foreach (Card::where('user_id', $target->id)->where('account_id', $account->id)->get() as $card) {
                if ($card->isLive()) {
                    app(CardService::class)->cancel($card, $actor);
                }
            }
            foreach (Goal::where('user_id', $target->id)->where('parent_account_id', $account->id)->where('status', '!=', 'closed')->get() as $goal) {
                try {
                    app(GoalService::class)->close($goal);
                } catch (TransferException $e) {
                    Audit::record('joint.goal_left_open', $goal, null, ['reason' => $e->reason], $actor->id);
                }
            }
            BillPayment::where('user_id', $target->id)->where('account_id', $account->id)->where('status', 'scheduled')->update(['status' => 'cancelled', 'recurrence' => 'none']);

            $row->update(['status' => 'removed', 'removed_at' => now()]);
            if ($row->role === 'primary') {
                $others->first()->update(['role' => 'primary']);
            }
            // Loans and loan applications that used this account move to one they still hold.
            $moved = app(\App\Services\Loans\LoanService::class)->moveOffAccount($target, $account, $actor);
            Audit::record('joint.holder_removed', $account, null, ['holder' => $target->id, 'how' => $how], $actor->id);

            return $moved;
        });
        MemberNotice::send($target, 'You were removed from a joint account', ['You are no longer a holder on a joint account. Your cards, savings goals and scheduled payments on it were stopped.', 'Sign in to see your accounts.'], 'Open your accounts', route('dashboard'));
        if ($moved['autopay_moved'] || $moved['autopay_off'] || $moved['applications_moved'] || $moved['applications_withdrawn']) {
            MemberNotice::send($target, 'Changes to your loans after a joint account change', array_filter([
                $moved['autopay_moved'] ? 'Automatic payments for loan '.implode(', ', $moved['autopay_moved']).' now come from another account of yours.' : null,
                $moved['autopay_off'] ? 'Automatic payments for loan '.implode(', ', $moved['autopay_off']).' were turned off because you have no other account to use. Turn them on again or pay by hand so you do not miss a payment.' : null,
                $moved['applications_moved'] ? 'The loan funds for application '.implode(', ', $moved['applications_moved']).' will go to another account of yours.' : null,
                $moved['applications_withdrawn'] ? 'Loan application '.implode(', ', $moved['applications_withdrawn']).' was withdrawn because you have no other account to receive the funds.' : null,
            ]), 'Open your loans', route('borrow.index'));
        }
    }

    private function assertManageable(User $by, Account $account): void
    {
        if (! $this->holderRow($account, $by)) {
            throw new InvalidArgumentException('You are not a holder of this account.');
        }
        if ($account->type !== 'standard' || $account->status !== 'active') {
            throw new InvalidArgumentException('This account cannot have joint holders.');
        }
    }

    private function reference(): string
    {
        do {
            $ref = 'JR'.random_int(10_000_000, 99_999_999);
        } while (JointRequest::where('reference', $ref)->exists());

        return $ref;
    }
}
