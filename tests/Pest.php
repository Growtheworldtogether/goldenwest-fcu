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

/** A signed-up, email-verified member with the starter savings and checking accounts. @return array{0: User, 1: Account, 2: Account} */
function verifiedMember(string $name = 'Maria Alvarez'): array
{
    $user = makeUser('user', $name);
    $user->forceFill(['first_name' => explode(' ', $name)[0], 'last_name' => explode(' ', $name)[1] ?? '', 'email_verified_at' => now()])->save();
    [$savings, $checking] = app(App\Services\AccountOpening::class)->openStarterAccounts($user);

    return [$user, $checking->refresh(), $savings->refresh()];
}

function deposit(Account $account, int $cents, string $description = 'Direct deposit, payroll'): App\Models\Transaction
{
    return app(TransferService::class)->execute(new TransferRequest(
        type: TransactionType::Deposit, amount: Money::cents($cents), idempotencyKey: 'dep:'.uniqid('', true),
        toAccountId: $account->id, memberDescription: $description,
    ));
}

function registrationData(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Linda', 'last_name' => 'Nguyen', 'email' => 'linda.nguyen@example.com',
        'phone' => '408-555-0149', 'dob' => '1985-04-12', 'ssn_last4' => '4821',
        'address_line1' => '1876 Story Rd', 'city' => 'San Jose', 'state' => 'CA', 'postal_code' => '95122',
        'field_of_membership' => 'lives_in_region', 'eligibility_state' => 'CA',
        'password' => 'Correct-Horse-9', 'password_confirmation' => 'Correct-Horse-9', 'terms' => '1',
    ], $overrides);
}

/** A staff member with a title. Two-step verification is on unless asked otherwise. */
function makeStaff(string $title = 'Manager', bool $twoFactor = true, string $name = 'Staff Person'): User
{
    $user = makeUser('admin', $name);
    $user->forceFill(['first_name' => explode(' ', $name)[0], 'last_name' => explode(' ', $name)[1] ?? '', 'email_verified_at' => now()])->save();
    App\Services\StaffDirectory::assign($user, $title);

    if ($twoFactor) {
        $user->forceFill([
            'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'two_factor_recovery_codes' => encrypt(json_encode(['aaaaaaaaaa-bbbbbbbbbb'])),
            'two_factor_confirmed_at' => now(),
        ])->save();
    }

    return $user->refresh();
}

function staffCode(): string
{
    return (new PragmaRX\Google2FA\Google2FA)->getCurrentOtp('JBSWY3DPEHPK3PXP');
}
