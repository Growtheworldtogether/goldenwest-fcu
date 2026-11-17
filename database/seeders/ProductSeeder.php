<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['GOLDEN-SAVINGS', 'Golden Savings', 'share_savings', 355, 500, 0],
            ['EVERYDAY-CHECKING', 'Everyday Checking', 'share_draft', 5, 0, 0],
            ['MONEY-MARKET', 'Money Market', 'money_market', 410, 1_000_000, 0],
            ['CERTIFICATE-12', '12-Month Certificate', 'certificate', 430, 50_000, 0],
        ] as [$code, $name, $kind, $bps, $min, $fee]) {
            Product::updateOrCreate(['code' => $code], [
                'name' => $name, 'kind' => $kind, 'dividend_rate_bps' => $bps, 'min_balance' => $min, 'monthly_fee' => $fee,
            ]);
        }
    }
}
