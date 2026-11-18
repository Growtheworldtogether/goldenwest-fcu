<?php

use App\Models\BrandSetting;
use App\Models\User;
use App\Support\Installer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->lock = sys_get_temp_dir().'/gw-lock-'.uniqid();
    $this->keyFile = sys_get_temp_dir().'/gw-key-'.uniqid();
    config(['setup.lock' => $this->lock, 'setup.key_file' => $this->keyFile, 'setup.check_in_tests' => true]);
    Installer::forget();
});

afterEach(function () {
    @unlink($this->lock);
    @unlink($this->keyFile);
    Installer::forget();
});

it('sends every page to setup while nothing is installed', function () {
    $this->get('/')->assertRedirect('/setup');
    $this->get('/login')->assertRedirect('/setup');
    $this->get('/setup')->assertOk()->assertSee('Confirm you own this site');
});

it('treats a site that already has accounts as installed and marks it', function () {
    makeUser();
    $this->get('/')->assertOk();
    expect(is_file($this->lock))->toBeTrue();
    $this->get('/setup')->assertNotFound();
});

it('hides setup once the lock exists', function () {
    file_put_contents($this->lock, 'x');
    Installer::forget();
    $this->get('/setup/union')->assertNotFound();
    $this->post('/setup/key')->assertNotFound();
});

it('refuses a wrong owner key and accepts the right one', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9']);
    $this->get('/setup/key')->assertOk()->assertSee('install-key.txt');
    $this->post('/setup/key', ['key' => 'WRONGKEY'])->assertSessionHasErrors('key');
    $this->post('/setup/key', ['key' => strtolower(Installer::key())])->assertRedirect('/setup/check');
});

it('requires the key even when APP_ENV is local, unless the browser is on the server itself', function () {
    config(['app.env' => 'local']);
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->post('/setup/key', ['key' => 'WRONGKEY'])->assertSessionHasErrors('key');
    // Same machine, but arriving through a proxy: still needs the key.
    $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])->withHeader('X-Forwarded-For', '203.0.113.9')->post('/setup/key', ['key' => 'WRONGKEY'])->assertSessionHasErrors('key');
    // On the server itself, no key.
    $this->flushHeaders();
    $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])->post('/setup/key', [])->assertRedirect('/setup/check');
});

it('locks the key step after five wrong tries', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.20']);
    foreach (range(1, 5) as $i) {
        $this->post('/setup/key', ['key' => 'WRONGKEY'.$i]);
    }
    $this->post('/setup/key', ['key' => Installer::key()])->assertSessionHasErrors('key');
    expect(session('errors')->first('key'))->toContain('Too many tries');
});

it('will not open a step before the ones before it are done', function () {
    $this->get('/setup/admin')->assertRedirect('/setup/key');
    $this->post('/setup/admin', [])->assertForbidden();
});

it('validates the credit union answers', function () {
    session(['setup_done' => ['key' => true, 'check' => true]]);
    $good = ['name' => 'Harbor Federal Credit Union', 'short_name' => 'Harbor', 'url' => 'https://www.harborfcu.org', 'support_email' => 'care@harborfcu.org',
        'support_phone' => '800-555-0100', 'support_hours' => 'Mon-Fri 8am - 6pm ET', 'routing_number' => '121478906', 'brand_color' => '#14403a', 'highlight_color' => '#f5bd02', 'tz' => 'America/New_York'];

    $this->post('/setup/union', [...$good, 'routing_number' => '123456789'])->assertSessionHasErrors('routing_number');
    $this->post('/setup/union', [...$good, 'brand_color' => '#ffee88'])->assertSessionHasErrors('brand_color');
    $this->post('/setup/union', [...$good, 'tz' => 'Mars/Base'])->assertSessionHasErrors('tz');
    $this->post('/setup/union', $good)->assertRedirect('/setup/database');
});

it('checks the administrator password rules', function () {
    session(['setup_done' => ['key' => true, 'check' => true, 'union' => true, 'database' => true, 'email' => true]]);
    $this->post('/setup/admin', ['name' => 'Ana Ruiz', 'email' => 'ana@harborfcu.org', 'password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');
    $this->post('/setup/admin', ['name' => 'Ana Ruiz', 'email' => 'ana@harborfcu.org', 'password' => 'Correct-Horse-9', 'password_confirmation' => 'Correct-Horse-9'])->assertRedirect('/setup/review');
});

it('refuses to reset without confirmation', function () {
    $this->artisan('platform:reset')->expectsConfirmation('This deletes ALL data in the database, including members and the ledger. Continue?', 'no')->assertFailed();
});

it('seeds reference data only by default', function () {
    $this->seed(\Database\Seeders\DatabaseSeeder::class);
    expect(User::count())->toBe(0)->and(BrandSetting::count())->toBe(0)->and(\App\Models\Product::count())->toBeGreaterThan(0);
});
