<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AccountRestriction;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/** Locks and unlocks. TransferService enforces them; this records who, why and when. */
final class RestrictionService
{
    public const TYPES = [
        AccountRestriction::FULL_LOCK => 'Full lock (no money in or out)',
        AccountRestriction::DEBIT_BLOCK => 'Debit block (no money out)',
        AccountRestriction::CREDIT_BLOCK => 'Credit block (no money in)',
    ];

    public function lock(User $admin, ?Account $account, ?User $member, string $type, string $reasonCode, string $reasonText): AccountRestriction
    {
        if (! $account && ! $member) {
            throw ValidationException::withMessages(['target' => 'Choose an account or the whole member.']);
        }
        if (! array_key_exists($type, self::TYPES) || trim($reasonText) === '') {
            throw ValidationException::withMessages(['reason_text' => 'A type and a reason are required.']);
        }

        $restriction = AccountRestriction::create([
            'account_id' => $account?->id, 'user_id' => $account ? null : $member->id,
            'type' => $type, 'reason_code' => $reasonCode, 'reason_text' => $reasonText,
            'locked_by' => $admin->id, 'locked_at' => now(),
        ]);
        Audit::record('restriction.created', $account ?? $member, null, ['type' => $type, 'reason' => $reasonCode, 'text' => $reasonText], $admin->id);

        return $restriction;
    }

    public function unlock(User $admin, AccountRestriction $restriction, string $note): AccountRestriction
    {
        if ($restriction->unlocked_at) {
            throw ValidationException::withMessages(['note' => 'This lock is already released.']);
        }
        if (trim($note) === '') {
            throw ValidationException::withMessages(['note' => 'A note is required to unlock.']);
        }

        $restriction->update(['unlocked_by' => $admin->id, 'unlocked_at' => now(), 'unlock_note' => $note]);
        Audit::record('restriction.released', $restriction->account ?? $restriction->user, ['type' => $restriction->type], ['note' => $note], $admin->id);

        return $restriction;
    }
}
