<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\AccountHolder;
use App\Models\User;

/** Every ownership check reads account_holders. */
class AccountPolicy
{
    public function view(User $user, Account $account): bool
    {
        return AccountHolder::where('account_id', $account->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();
    }

    public function transfer(User $user, Account $account): bool
    {
        return $this->view($user, $account) && $account->status === 'active';
    }
}
