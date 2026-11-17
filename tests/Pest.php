<?php

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\AccountHolder;
use App\Models\Product;
use App\Models\User;
use App\Services\Ledger\TransferRequest;
use App\Services\Ledger\TransferService;
use App\Support\AccountNumber;
use App\Support\Money;
use Database\Seeders\ProductSeeder;
use Database\Seeders\ReasonCodeSeeder;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature');

/** Seed the reference rows every money test needs. */
function seedReference(): void
{
    (new ProductSeeder)->run();
    (new ReasonCodeSeeder)->run();
}

function makeUser(string $role = 'user', string $name = 'Maria Alvarez'): User
{
    static $n = 0;
    $n++;

    $user = User::create(['name' => $name, 'email' => "member{$n}-".uniqid().'@example.com', 'password' => 'a-long-test-password-1']);
    $user->forceFill(['role' => $role, 'member_number' => $role === 'user' ? AccountNumber::member() : null])->save();

    return $user;
}

/** An open account owned by $user, optionally funded through the ledger. */
function makeAccount(?User $user = null, int $cents = 0, string $product = 'EVERYDAY-CHECKING', ?int $overdraftId = null): Account
{
    $user ??= makeUser();
    $account = Account::create([
        'product_id' => Product::where('code', $product)->firstOrFail()->id,
        'account_number' => AccountNumber::account(),
        'overdraft_account_id' => $overdraftId,
    ]);
    AccountHolder::create(['account_id' => $account->id, 'user_id' => $user->id]);

    if ($cents > 0) {
        app(TransferService::class)->execute(new TransferRequest(
            type: TransactionType::Deposit,
            amount: Money::cents($cents),
            idempotencyKey: 'fund:'.uniqid('', true),
            toAccountId: $account->id,
            memberDescription: 'Opening deposit',
        ));
    }

    return $account->refresh();
}

function transfer(Account $from, Account $to, int $cents, ?string $key = null, TransactionType $type = TransactionType::Transfer, ?int $by = null): App\Models\Transaction
{
    return app(TransferService::class)->execute(new TransferRequest(
        type: $type,
        amount: Money::cents($cents),
        idempotencyKey: $key ?? 'tx:'.uniqid('', true),
        fromAccountId: $from->id,
        toAccountId: $to->id,
        initiatedBy: $by,
    ));
}
