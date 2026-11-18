<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Reference data only: products and reason codes. No members, no staff, no history.
 * The first administrator and the brand settings come from the setup wizard.
 * To load a believable demo credit union on a throwaway copy: php artisan db:seed --class=DemoDataSeeder
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([ProductSeeder::class, ReasonCodeSeeder::class]);
    }
}
