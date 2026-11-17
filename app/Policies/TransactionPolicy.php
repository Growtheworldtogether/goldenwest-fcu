<?php

namespace App\Policies;

use App\Models\AccountHolder;
use App\Models\Transaction;
use App\Models\User;

class TransactionPolicy
{
    /** A member can see a transaction only when one of their own accounts is on it. */
    public function view(User $user, Transaction $transaction): bool
    {
        $accountIds = array_filter([$transaction->from_account_id, $transaction->to_account_id]);

        return $accountIds !== [] && AccountHolder::where('user_id', $user->id)
            ->where('status', 'active')
            ->whereIn('account_id', $accountIds)
            ->exists();
    }
}
