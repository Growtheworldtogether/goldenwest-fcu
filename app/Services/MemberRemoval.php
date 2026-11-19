<?php

namespace App\Services;

use App\Models\Account;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Removing a member. A member who never used their account (no transactions, loans, cards, crypto, wires, deposits,
 * bill payments, tickets or staff visits) is erased completely. Anyone else is closed: sign in off, personal details
 * wiped, accounts closed, and the money records kept under a masked name, because the ledger and the audit trail
 * have to stay whole. Only a Super Admin can do either.
 */
class MemberRemoval
{
    /** Tables whose rows mean the member used their account. */
    private const HISTORY = ['loans', 'loan_applications', 'cards', 'crypto_transactions', 'wire_transfers', 'deposits', 'bill_payments', 'tickets'];

    /** @return 'erased'|'closed' */
    public function remove(User $member, User $staff, string $reason): string
    {
        $this->guard($member, $staff);
        $erase = $this->canErase($member);
        $label = $member->member_number.' '.$member->name;

        if ($erase) {
            try {
                $this->erase($member);
                Audit::record('member.erased', null, ['member_number' => $member->member_number, 'name' => $member->name, 'email' => $member->email], ['reason' => $reason], $staff->id);

                return 'erased';
            } catch (QueryException) {
                // Something still points at this member. Nothing was changed; close them instead.
            }
        }

        $this->close($member);
        Audit::record('member.closed', $member, ['name' => $label], ['reason' => $reason, 'erased' => false], $staff->id);

        return 'closed';
    }

    /** What would happen, for the confirmation box. */
    public function plan(User $member): string
    {
        return $this->canErase($member) ? 'erase' : 'close';
    }

    public function guard(User $member, User $staff): void
    {
        if (! StaffDirectory::isSuperAdmin($staff)) {
            throw new InvalidArgumentException('Only a Super Admin can delete or close a member.');
        }
        if ($member->isAdmin() || $member->is($staff)) {
            throw new InvalidArgumentException('Staff accounts are removed from Settings, not here.');
        }
        if ($member->status === 'closed') {
            throw new InvalidArgumentException('This member is already closed.');
        }
        $accounts = $this->accountIds($member);
        if ($accounts && Account::whereIn('id', $accounts)->where(fn ($q) => $q->where('balance', '!=', 0)->orWhere('held_balance', '!=', 0))->exists()) {
            throw new InvalidArgumentException('An account still has money in it or on hold. Move it out first.');
        }
        if (DB::table('loans')->where('user_id', $member->id)->where('status', '!=', 'paid_off')->exists()) {
            throw new InvalidArgumentException('This member still has a loan that is not paid off.');
        }
        if (DB::table('crypto_wallets')->where('user_id', $member->id)->where(fn ($q) => $q->where('balance', '>', 0)->orWhere('held', '>', 0))->exists()) {
            throw new InvalidArgumentException('A crypto wallet still has a balance. Clear it out first.');
        }
        if ($accounts && DB::table('transactions')->whereIn('status', ['pending', 'held'])->where(fn ($q) => $q->whereIn('from_account_id', $accounts)->orWhereIn('to_account_id', $accounts))->exists()) {
            throw new InvalidArgumentException('A transaction is still waiting for review. Finish it first.');
        }
        $shared = DB::table('account_holders')->whereIn('account_id', $accounts)->where('user_id', '!=', $member->id)->where('status', 'active')->exists();
        if ($shared) {
            throw new InvalidArgumentException('This member shares an account with someone else. Remove them from the joint account first.');
        }
    }

    public function canErase(User $member): bool
    {
        $accounts = $this->accountIds($member);
        if ($accounts && DB::table('transactions')->where(fn ($q) => $q->whereIn('from_account_id', $accounts)->orWhereIn('to_account_id', $accounts))->exists()) {
            return false;
        }
        foreach (self::HISTORY as $table) {
            if (Schema::hasTable($table) && DB::table($table)->where('user_id', $member->id)->exists()) {
                return false;
            }
        }

        return ! DB::table('impersonations')->where('member_id', $member->id)->exists();
    }

    /** @return list<int> */
    private function accountIds(User $member): array
    {
        return DB::table('account_holders')->where('user_id', $member->id)->pluck('account_id')->map(fn ($i) => (int) $i)->all();
    }

    private function erase(User $member): void
    {
        $files = [];
        DB::transaction(function () use ($member, &$files) {
            $accounts = $this->accountIds($member);
            $files = DB::table('kyc_documents')->where('user_id', $member->id)->pluck('path')->all();

            DB::table('account_holders')->where('user_id', $member->id)->delete();
            if ($accounts) {
                DB::table('account_restrictions')->whereIn('account_id', $accounts)->delete();
                DB::table('accounts')->whereIn('overdraft_account_id', $accounts)->update(['overdraft_account_id' => null]);
                DB::table('accounts')->whereIn('id', $accounts)->delete();
            }

            // Everything else that belongs to this member: every table with a key to users, retried until nothing is left.
            $links = [];
            foreach (Schema::getTableListing(schemaQualified: false) as $table) {
                foreach (Schema::getForeignKeys($table) as $fk) {
                    if (($fk['foreign_table'] ?? null) === 'users' && $table !== 'audit_logs') {
                        foreach ($fk['columns'] as $col) {
                            $links[] = [$table, $col];
                        }
                    }
                }
            }
            for ($pass = 0; $pass < 6; $pass++) {
                $left = 0;
                foreach ($links as [$table, $col]) {
                    try {
                        DB::table($table)->where($col, $member->id)->delete();
                    } catch (QueryException) {
                        $left++;
                    }
                }
                if (! $left) {
                    break;
                }
            }
            DB::table('sessions')->where('user_id', $member->id)->delete();
            if (Schema::hasTable('notifications')) {
                DB::table('notifications')->where('notifiable_id', $member->id)->where('notifiable_type', $member->getMorphClass())->delete();
            }
            DB::table('users')->where('id', $member->id)->delete();
        });

        foreach ($files as $path) {
            try {
                Storage::disk('local')->delete($path);
            } catch (\Throwable) {
                // The record is gone; a stray file is not a reason to fail.
            }
        }
    }

    private function close(User $member): void
    {
        DB::transaction(function () use ($member) {
            $accounts = $this->accountIds($member);
            if ($accounts) {
                DB::table('accounts')->whereIn('id', $accounts)->update(['status' => 'closed']);
            }
            $member->forceFill([
                'name' => 'Closed member '.$member->member_number, 'first_name' => 'Closed', 'last_name' => 'member',
                'email' => 'closed-'.$member->id.'@closed.invalid', 'phone' => null, 'dob' => null, 'address_line1' => null, 'city' => null,
                'state' => null, 'postal_code' => null, 'status' => 'closed', 'password' => Str::random(48), 'remember_token' => null,
                'two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null,
                'locked_until' => null, 'notification_preferences' => null, 'security_last_question' => null,
            ])->save();
            DB::table('member_profiles')->where('user_id', $member->id)->update(['ssn_last4' => null, 'referred_by_member_number' => null]);
            foreach (['user_security_answers', 'login_devices', 'login_ips', 'sessions'] as $table) {
                DB::table($table)->where('user_id', $member->id)->delete();
            }
            DB::table('cards')->where('user_id', $member->id)->whereIn('status', ['requested', 'active', 'frozen'])->update(['status' => 'cancelled']);
        });
    }
}
