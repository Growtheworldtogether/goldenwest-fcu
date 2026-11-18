<?php

use App\Services\WireService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('runs the platform on Eastern time', function () {
    expect(config('app.timezone'))->toBe('America/New_York')->and(now()->timezoneName)->toBe('America/New_York');
});

it('dates a late evening Eastern transaction with that day, not the next UTC day', function () {
    $this->travelTo(Carbon::parse('2026-11-18 21:30', 'America/New_York')); // 02:30 UTC the next day
    expect(today()->toDateString())->toBe('2026-11-18');
});

it('works out the wire cutoff in Eastern time', function () {
    $this->travelTo(Carbon::parse('2026-11-18 13:30', 'America/New_York')); // a Wednesday, before the 2 pm cutoff
    expect(WireService::processOn()->toDateString())->toBe('2026-11-18');
    $this->travelTo(Carbon::parse('2026-11-18 14:30', 'America/New_York'));
    expect(WireService::processOn()->toDateString())->toBe('2026-11-19');
});

it('shows the time zone next to times that need one', function () {
    seedReference();
    [$user] = verifiedMember();
    $admin = makeStaff('Super Admin');
    $this->actingAs($admin)->get('/admin/audit')->assertOk()->assertSee('When (E', false);
});
