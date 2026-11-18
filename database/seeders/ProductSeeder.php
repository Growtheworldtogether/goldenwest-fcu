<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['GOLDEN-SAVINGS', 'Golden Savings', 'share_savings', 355, 0, 0],
            ['EVERYDAY-CHECKING', 'Everyday Checking', 'share_draft', 5, 0, 0],
            ['MONEY-MARKET', 'Money Market', 'money_market', 410, 1_000_000, 0],
            ['CERTIFICATE-12', '12-Month Certificate', 'certificate', 430, 50_000, 0],
            ['BUSINESS-CHECKING', 'Business Checking', 'share_draft', 5, 0, 0, 'business'],
            ['BUSINESS-SAVINGS', 'Business Savings', 'share_savings', 300, 0, 0, 'business'],
        ] as $row) {
            [$code, $name, $kind, $bps, $min, $fee] = $row;
            $segment = $row[6] ?? 'personal';
            Product::updateOrCreate(['code' => $code], [
                'name' => $name, 'kind' => $kind, 'segment' => $segment, 'dividend_rate_bps' => $bps, 'min_balance' => $min, 'monthly_fee' => $fee,
            ]);
        }
    }
}
