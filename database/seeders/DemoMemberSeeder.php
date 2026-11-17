<?php

namespace Database\Seeders;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\AccountHolder;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Ledger\TransferException;
use App\Services\Ledger\TransferRequest;
use App\Services\Ledger\TransferService;
use App\Support\AccountNumber;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Ten members with accounts and about 75 days of history, all posted through TransferService
 * so the ledger, balances and audit rows are exactly what the app would have produced.
 */
class DemoMemberSeeder extends Seeder
{
    /** [first, last, street, city, state, zip, phone, monthly pay in dollars, has money market] */
    private array $members = [
        ['Maria', 'Alvarez', '2418 Telegraph Ave', 'Oakland', 'CA', '94612', '510-555-0134', 3850, true],
        ['James', 'Carter', '915 Fulton St', 'Sacramento', 'CA', '95814', '916-555-0172', 3200, false],
        ['Linda', 'Nguyen', '1876 Story Rd', 'San Jose', 'CA', '95122', '408-555-0149', 4600, true],
        ['Robert', 'Thompson', '3320 Spring Mountain Rd', 'Las Vegas', 'NV', '89102', '702-555-0118', 4100, true],
        ['Priya', 'Patel', '740 E Camelback Rd', 'Phoenix', 'AZ', '85014', '602-555-0163', 5200, false],
        ['Daniel', 'Kim', '1201 NW Lovejoy St', 'Portland', 'OR', '97209', '503-555-0127', 3500, false],
        ['Angela', 'Brooks', '4410 Fremont Ave N', 'Seattle', 'WA', '98103', '206-555-0191', 4400, true],
        ['Michael', 'Rossi', '602 Island Ave', 'San Diego', 'CA', '92101', '619-555-0156', 3900, false],
        ['Sofia', 'Hernandez', '2250 N Campbell Ave', 'Tucson', 'AZ', '85719', '520-555-0185', 2900, false],
        ['William', 'Foster', '515 S Virginia St', 'Reno', 'NV', '89501', '775-555-0102', 3600, false],
    ];

    private array $utility = ['CA' => 'Pacific Gas & Electric', 'NV' => 'NV Energy', 'AZ' => 'Arizona Public Service', 'OR' => 'Portland General Electric', 'WA' => 'Seattle City Light'];

    /** [merchant, min dollars, max dollars, category weight] */
    private array $shops = [
        ['Whole Foods Market', 38, 165, 5], ['Trader Joe\'s', 28, 96, 5], ['Costco Wholesale', 92, 268, 2],
        ['Target', 22, 142, 4], ['Shell', 36, 74, 4], ['Chevron', 38, 78, 3], ['Starbucks', 5, 14, 8],
        ['Amazon', 14, 128, 5], ['Safeway', 30, 120, 4], ['CVS Pharmacy', 8, 46, 3], ['Chipotle Mexican Grill', 11, 24, 4],
        ['The Home Depot', 24, 210, 2], ['Uber', 9, 38, 3], ['Delta Air Lines', 184, 546, 1],
    ];

    public function run(TransferService $ledger): void
    {
        $password = env('SEED_MEMBER_PASSWORD', 'GoldenWest2026!');
        $savings = Product::where('code', 'GOLDEN-SAVINGS')->firstOrFail();
        $checking = Product::where('code', 'EVERYDAY-CHECKING')->firstOrFail();
        $market = Product::where('code', 'MONEY-MARKET')->firstOrFail();
        $admin = User::where('role', 'admin')->first();

        foreach ($this->members as $i => [$first, $last, $street, $city, $state, $zip, $phone, $pay, $hasMarket]) {
            mt_srand(7000 + $i);
            $start = now()->startOfDay()->subDays(75);

            $user = User::create([
                'name' => "$first $last",
                'first_name' => $first, 'last_name' => $last,
                'email' => strtolower("$first.$last").'@example.com',
                'password' => $password,
                'phone' => $phone,
                'dob' => Carbon::create(1968 + ($i * 3) % 30, 1 + ($i * 5) % 12, 3 + ($i * 7) % 24),
                'address_line1' => $street, 'city' => $city, 'state' => $state, 'postal_code' => $zip,
            ]);
            $user->forceFill(['member_number' => AccountNumber::member(), 'kyc_status' => 'verified', 'email_verified_at' => $start])->save();

            $sav = $this->open($user, $savings, 'Golden Savings');
            $chk = $this->open($user, $checking, 'Everyday Checking', $sav->id);
            $mm = $hasMarket ? $this->open($user, $market, 'Money Market') : null;

            $n = 0;
            $post = function (TransactionType $type, int $cents, ?int $from, ?int $to, string $desc, ?string $memo = null, bool $review = false) use ($ledger, $user, &$n) {
                try {
                    return $ledger->execute(new TransferRequest(
                        type: $type, amount: Money::cents($cents), idempotencyKey: "seed:{$user->id}:".++$n,
                        fromAccountId: $from, toAccountId: $to, initiatedBy: $user->id,
                        memo: $memo, memberDescription: $desc, requireReview: $review,
                    ));
                } catch (TransferException) {
                    return null; // not enough money that day: skip it, like a real statement would
                }
            };

            // Opening deposits
            Carbon::setTestNow($start->copy()->setTime(10, 15));
            $post(TransactionType::Deposit, 100 * mt_rand(1400, 6200), null, $sav->id, 'Opening deposit');
            $post(TransactionType::Deposit, 100 * ($pay + mt_rand(0, 600)), null, $chk->id, 'Direct deposit, payroll');
            if ($mm) {
                $post(TransactionType::Deposit, 100 * mt_rand(11_000, 26_000), null, $mm->id, 'Deposit');
            }

            for ($d = 1; $d <= 75; $d++) {
                $day = $start->copy()->addDays($d);
                Carbon::setTestNow($day->copy()->setTime(mt_rand(8, 19), mt_rand(0, 59)));

                if (in_array($day->day, [1, 15], true)) {
                    $post(TransactionType::Deposit, intdiv(100 * $pay, 2) + 100 * mt_rand(0, 90), null, $chk->id, 'Direct deposit, payroll');
                }
                if ($day->day === 3) {
                    $post(TransactionType::BillPayment, 100 * mt_rand(62, 168) + mt_rand(0, 99), $chk->id, null, $this->utility[$state], 'Utilities');
                    $post(TransactionType::BillPayment, 1549, $chk->id, null, 'Netflix', 'Entertainment');
                }
                if ($day->day === 5) {
                    $post(TransactionType::AchOut, 100 * (1450 + 75 * ($i % 6)), $chk->id, null, 'Rent payment', 'Housing');
                }
                if ($day->day === 20) {
                    $post(TransactionType::Transfer, 100 * mt_rand(2, 6) * 100, $chk->id, $sav->id, 'Transfer to savings');
                }

                for ($k = mt_rand(0, 2); $k > 0; $k--) {
                    $shop = $this->pick();
                    $cents = 100 * mt_rand($shop[1], $shop[2]) + mt_rand(0, 99);
                    $post(TransactionType::CardPurchase, $cents, $chk->id, null, $shop[0], 'Card purchase');
                }
            }

            Carbon::setTestNow();

            // Staff-queue examples so the admin area has something real to review.
            if ($i === 3) {
                $tx = $post(TransactionType::WireOut, 820_000, $mm->id, null, 'Wire to Las Vegas Title & Escrow', 'Home purchase');
                \App\Models\UserFeature::create(['user_id' => $user->id, 'feature' => 'wires', 'enabled_at' => now(), 'note' => 'Home purchase']);
                if ($tx instanceof Transaction) {
                    \App\Models\WireTransfer::create(['user_id' => $user->id, 'transaction_id' => $tx->id, 'recipient_name' => 'Las Vegas Title & Escrow', 'recipient_address' => '3960 Howard Hughes Pkwy, Las Vegas, NV 89169', 'bank_name' => 'Silver State Bank', 'routing_number' => '021000021', 'account_number' => '4400118827', 'last4' => '8827', 'purpose' => 'Home purchase closing', 'process_on' => now()->toDateString()]);
                }
            }
            if ($i === 4 && $admin) {
                $tx = $post(TransactionType::AchOut, 600_000, $chk->id, null, 'ACH to external account');
                if ($tx instanceof Transaction) {
                    $ledger->hold($tx, $admin, 'verification_required', 'We called to confirm this payee and have not reached you yet.', now()->addDays(2));
                }
            }
        }
    }

    private function open(User $user, Product $product, string $nickname, ?int $overdraftId = null): Account
    {
        $account = Account::create([
            'product_id' => $product->id,
            'account_number' => AccountNumber::account(),
            'nickname' => $nickname,
            'overdraft_account_id' => $overdraftId,
        ]);
        AccountHolder::create(['account_id' => $account->id, 'user_id' => $user->id, 'role' => 'primary']);

        return $account;
    }

    private function pick(): array
    {
        $pool = [];
        foreach ($this->shops as $shop) {
            for ($k = 0; $k < $shop[3]; $k++) {
                $pool[] = $shop;
            }
        }

        return $pool[mt_rand(0, count($pool) - 1)];
    }
}
