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
        $admin->forceFill(['kyc_status' => 'verified', 'email_verified_at' => now()])->save();
        \App\Services\StaffDirectory::assign($admin, 'Super Admin');

        foreach ([['Teller', 'Priya', 'Nair', 'teller'], ['Manager', 'Daniel', 'Brooks', 'manager'], ['Compliance', 'Rachel', 'Kim', 'compliance'], ['Support Agent', 'Tyler', 'Moore', 'support']] as [$title, $first, $last, $handle]) {
            $staff = User::create(['name' => "$first $last", 'first_name' => $first, 'last_name' => $last, 'email' => "$handle@example.com", 'password' => $adminPassword]);
            $staff->forceFill(['kyc_status' => 'verified', 'email_verified_at' => now()])->save();
            \App\Services\StaffDirectory::assign($staff, $title);
        }

        $this->call(DemoMemberSeeder::class);

        $this->command?->info('Staff (Super Admin admin@example.com, teller@, manager@, compliance@, support@example.com) all use: '.$adminPassword);
        $this->command?->info('Members: <first>.<last>@example.com / '.env('SEED_MEMBER_PASSWORD', 'GoldenWest2026!'));
    }
}
