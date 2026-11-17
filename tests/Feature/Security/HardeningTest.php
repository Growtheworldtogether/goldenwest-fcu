<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(fn () => seedReference());

it('sends security headers on public and signed-in pages', function () {
    $public = $this->get('/');
    $public->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    $csp = $public->headers->get('Content-Security-Policy');
    expect($csp)->toContain("default-src 'self'")->toContain("frame-ancestors 'none'")->toContain("object-src 'none'")->toContain("form-action 'self'");
    expect($public->headers->get('Permissions-Policy'))->toContain('camera=()');
    expect($public->headers->has('Strict-Transport-Security'))->toBeFalse();
    expect($this->get('https://localhost/')->headers->get('Strict-Transport-Security'))->toContain('max-age=31536000');

    [$member] = verifiedMember('Maria Alvarez');
    $this->actingAs($member)->get('/dashboard')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
});

it('loads nothing from another host', function () {
    foreach (['/', '/login', '/register'] as $path) {
        $html = $this->get($path)->getContent();
        preg_match_all('/<(?:script|link|img|iframe)[^>]+(?:src|href)="(https?:\/\/[^"]+)"/i', $html, $m);
        $external = array_filter($m[1], fn ($u) => ! str_starts_with($u, config('app.url')) && ! str_contains($u, 'localhost'));
        expect($external)->toBeEmpty();
    }
});

it('limits the sign-up, password reset and verification forms by address', function () {
    RateLimiter::clear('auth-forms');
    for ($i = 0; $i < 8; $i++) {
        $this->post('/forgot-password', ['email' => 'nobody@example.com'])->assertStatus(302);
    }
    $this->post('/forgot-password', ['email' => 'nobody@example.com'])->assertStatus(429);
    $this->post('/register', [])->assertStatus(429);
});

it('limits sign-in attempts per email and address', function () {
    [$member] = verifiedMember('Maria Alvarez');
    for ($i = 0; $i < 5; $i++) {
        $this->post('/login', ['email' => $member->email, 'password' => 'wrong-password-here']);
    }
    $this->post('/login', ['email' => $member->email, 'password' => 'a-long-test-password-1'])->assertStatus(429);
});

it('limits how fast a signed-in member can hit the portal', function () {
    [$member] = verifiedMember('Maria Alvarez');
    $this->actingAs($member);
    for ($i = 0; $i < 180; $i++) {
        $this->get('/dashboard');
    }
    $this->get('/dashboard')->assertStatus(429);
});
