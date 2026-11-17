<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('Demo data is never seeded in production.');

            return;
        }

        $this->call([ProductSeeder::class, ReasonCodeSeeder::class]);

        $adminPassword = env('SEED_ADMIN_PASSWORD') ?: Str::password(18);
        $admin = User::create([
            'name' => 'Alexandra Reyes', 'first_name' => 'Alexandra', 'last_name' => 'Reyes',
            'email' => 'admin@example.com', 'password' => $adminPassword,
        ]);
        $admin->forceFill(['role' => 'admin', 'kyc_status' => 'verified', 'email_verified_at' => now()])->save();

        $this->call(DemoMemberSeeder::class);

        $this->command?->info('Super Admin: admin@example.com / '.$adminPassword);
        $this->command?->info('Members: <first>.<last>@example.com / '.env('SEED_MEMBER_PASSWORD', 'GoldenWest2026!'));
    }
}
