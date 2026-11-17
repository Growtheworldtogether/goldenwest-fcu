<?php

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;

/** Every staff permission check. Titles map to permissions in config/admin.php. */
class StaffPolicy
{
    private function has(User $u, string $permission): bool
    {
        return $u->isAdmin() && $u->checkPermissionTo($permission);
    }

    public function lookupMembers(User $u): bool { return $this->has($u, 'members.lookup'); }
    public function postDeposits(User $u): bool { return $this->has($u, 'deposits.post'); }
    public function lockAccounts(User $u): bool { return $this->has($u, 'accounts.lock'); }
    public function viewAudit(User $u): bool { return $this->has($u, 'audit.view'); }
    public function answerInquiries(User $u): bool { return $this->has($u, 'tickets.answer'); }
    public function toggleFeatures(User $u): bool { return $this->has($u, 'features.toggle'); }
    public function approveCards(User $u): bool { return $this->has($u, 'approvals.manage'); }
    public function approveLoans(User $u): bool { return $this->has($u, 'approvals.manage'); }
    public function approveCrypto(User $u): bool { return $this->has($u, 'approvals.manage'); }
    public function reviewKyc(User $u): bool { return $this->has($u, 'kyc.review'); }
    /** Ending a new external account's waiting period early is a manager decision. */
    public function waivePayeeWait(User $u): bool { return $this->has($u, 'transactions.approve'); }
    public function editSite(User $u): bool { return $this->has($u, 'site.edit'); }
    public function manageAdmin(User $u): bool { return $this->has($u, 'admin.manage'); }

    /** Tellers decide small amounts only. Managers, Compliance and Super Admin decide any amount. */
    public function decideTransaction(User $u, Transaction $tx): bool
    {
        return $this->has($u, 'transactions.approve')
            || ($this->has($u, 'transactions.approve.small') && $tx->amount->cents <= config('admin.teller_approval_limit'));
    }

    public function viewQueue(User $u): bool
    {
        return $this->has($u, 'transactions.approve') || $this->has($u, 'transactions.approve.small');
    }

    /** Managers adjust up to a limit. Super Admin has no limit. */
    public function adjustBalance(User $u, int $cents = 0): bool
    {
        return $this->has($u, 'balances.adjust')
            || ($this->has($u, 'balances.adjust.limited') && $cents <= config('admin.manager_adjustment_limit'));
    }

    public function canAdjust(User $u): bool
    {
        return $this->has($u, 'balances.adjust') || $this->has($u, 'balances.adjust.limited');
    }

    public function impersonate(User $u, User $member): bool
    {
        return $this->has($u, 'impersonate') && ! $member->isAdmin() && ! $member->is($u);
    }
}
