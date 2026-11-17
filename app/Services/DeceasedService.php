<?php

namespace App\Services;

use App\Models\AccountHolder;
use App\Models\AccountRestriction;
use App\Models\Card;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * Staff record that a member has died. Their sign-in stops and their accounts are protected, then staff pay out
 * through documented adjustments. A joint account stays open for the surviving holder.
 */
class DeceasedService
{
    public function mark(User $member, User $staff, string $note): void
    {
        if ($member->isAdmin() || $member->is($staff)) {
            throw new InvalidArgumentException('That is not possible for this person.');
        }
        if ($member->status === 'deceased') {
            throw new InvalidArgumentException('This member is already marked as deceased.');
        }
        if (trim($note) === '') {
            throw new InvalidArgumentException('Add a note about what was received, such as the death certificate.');
        }

        DB::transaction(function () use ($member, $staff, $note) {
            $locked = 0;
            $left = 0;
            foreach (AccountHolder::where('user_id', $member->id)->where('status', 'active')->get() as $row) {
                $account = $row->account;
                $survivors = AccountHolder::where('account_id', $account->id)->where('status', 'active')->where('user_id', '!=', $member->id)->exists();
                if ($survivors) {
                    app(JointService::class)->removeHolder($account, $member, $staff, 'deceased');
                    $left++;
                } else {
                    app(RestrictionService::class)->lock($staff, $account, null, AccountRestriction::FULL_LOCK, 'deceased_member', $note);
                    $locked++;
                }
            }
            foreach (Card::where('user_id', $member->id)->get() as $card) {
                if ($card->isLive()) {
                    app(CardService::class)->cancel($card, $staff);
                }
            }
            $member->forceFill(['status' => 'deceased', 'deceased_at' => now()])->save();
            if (Schema::hasTable('sessions')) {
                DB::table('sessions')->where('user_id', $member->id)->delete();
            }
            Audit::record('member.deceased', $member, null, ['note' => $note, 'accounts_locked' => $locked, 'joint_accounts_kept_open' => $left], $staff->id);
        });
    }
}
