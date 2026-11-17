<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a staff account with the chosen title', function () {
    $this->artisan('admin:create', ['email' => 'new.admin@example.com', '--name' => 'Nora Whitfield', '--title' => 'Manager'])
        ->expectsQuestion('Password (12 or more characters)', 'Str0ng-Passphrase-77')->assertSuccessful();
    $u = User::where('email', 'new.admin@example.com')->firstOrFail();
    expect($u->isAdmin())->toBeTrue()->and($u->adminProfile->title)->toBe('Manager')->and($u->hasRole('Manager'))->toBeTrue()
        ->and(Hash::check('Str0ng-Passphrase-77', $u->password))->toBeTrue();
});

it('refuses a weak password, a duplicate email and an unknown title', function () {
    $this->artisan('admin:create', ['email' => 'a@example.com', '--name' => 'Nora Whitfield'])->expectsQuestion('Password (12 or more characters)', 'short')->assertFailed();
    $this->artisan('admin:create', ['email' => 'a@example.com', '--name' => 'Nora Whitfield', '--title' => 'Owner'])->assertFailed();
    expect(User::where('email', 'a@example.com')->exists())->toBeFalse();
});
