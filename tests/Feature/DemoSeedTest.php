<?php

use App\Models\Announcement;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds a believable credit union that reconciles to the ledger', function () {
    $this->seed(DatabaseSeeder::class);

    expect(User::where('role', 'admin')->count())->toBe(5)->and(User::where('role', 'user')->count())->toBe(10)
        ->and(Loan::where('status', 'active')->count())->toBe(1)->and(LoanApplication::whereIn('status', ['submitted', 'in_review'])->count())->toBe(1)
        ->and(Announcement::count())->toBe(1);
    $this->artisan('ledger:verify')->assertSuccessful();

    // No placeholder wording anywhere a member could read it.
    foreach (['Test Transaction', 'User 1', 'lorem', 'placeholder'] as $bad) {
        expect(App\Models\Transaction::where('member_description', 'like', "%$bad%")->exists())->toBeFalse();
    }
});

it('refuses to seed demo data in production', function () {
    app()->detectEnvironment(fn () => 'production');
    $this->seed(DatabaseSeeder::class);
    expect(User::count())->toBe(0);
});
