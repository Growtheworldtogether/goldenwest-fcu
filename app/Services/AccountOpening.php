<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AccountHolder;
use App\Models\Product;
use App\Models\User;
use App\Support\AccountNumber;

/** Opens accounts for a member. Opening is free and needs no deposit. */
final class AccountOpening
{
    public function open(User $user, string $productCode, ?string $nickname = null, ?int $overdraftAccountId = null): Account
    {
        $product = Product::where('code', $productCode)->firstOrFail();

        $account = Account::create([
            'product_id' => $product->id,
            'account_number' => AccountNumber::account(),
            'nickname' => $nickname ?: $product->name,
            'overdraft_account_id' => $overdraftAccountId,
        ]);
        AccountHolder::create(['account_id' => $account->id, 'user_id' => $user->id, 'role' => 'primary']);

        Audit::record('account.opened', $account, null, ['product' => $product->code], $user->id);

        return $account;
    }

    /** Savings and checking, with savings linked as overdraft protection. */
    public function openStarterAccounts(User $user): array
    {
        $savings = $this->open($user, 'GOLDEN-SAVINGS');
        $checking = $this->open($user, 'EVERYDAY-CHECKING', overdraftAccountId: $savings->id);

        return [$savings, $checking];
    }
}
