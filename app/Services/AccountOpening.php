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

    /** What a new member chose on the join form: both accounts, savings only or checking only. */
    public function openChosen(User $user, string $choice): array
    {
        return match ($choice) {
            'savings' => [$this->open($user, 'GOLDEN-SAVINGS')],
            'checking' => [$this->open($user, 'EVERYDAY-CHECKING')],
            default => $this->openStarterAccounts($user),
        };
    }

    /** Business accounts sit under the owner's membership. They get a higher daily limit and no overdraft link. */
    public function openBusiness(User $user, string $choice): array
    {
        $codes = match ($choice) {
            'checking' => ['BUSINESS-CHECKING'],
            'savings' => ['BUSINESS-SAVINGS'],
            default => ['BUSINESS-CHECKING', 'BUSINESS-SAVINGS'],
        };

        return array_map(function (string $code) use ($user) {
            $account = $this->open($user, $code);
            $account->update(['daily_limit' => (int) config('bank.business_daily_limit')]);

            return $account;
        }, $codes);
    }
}
