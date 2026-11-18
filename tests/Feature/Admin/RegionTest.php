<?php

use App\Models\Setting;
use App\Support\Region;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => seedReference());

it('defaults to the five original states', function () {
    expect(Region::codes())->toBe(['AZ', 'CA', 'NV', 'OR', 'WA']);
});

it('lets a super admin change the eligible states and the join form follows', function () {
    $super = makeStaff('Super Admin');
    $this->actingAs($super)->put('/admin/settings', ['region_form' => 1, 'region_states' => ['CA', 'TX', 'NY']])->assertSessionHasNoErrors();
    expect(Region::codes())->toBe(['CA', 'NY', 'TX']);
    expect(Region::sentence('or'))->toBe('California, New York or Texas');
    auth()->logout();
    $this->get('/register')->assertOk()->assertSee('Texas', false)->assertSee('connection to California, New York or Texas', false);
});

it('refuses an empty list or an unknown state', function () {
    $super = makeStaff('Super Admin');
    $this->actingAs($super)->put('/admin/settings', ['region_form' => 1])->assertSessionHasErrors('region_states');
    $this->actingAs($super)->put('/admin/settings', ['region_form' => 1, 'region_states' => ['ZZ']])->assertSessionHasErrors('region_states.0');
    expect(Setting::find('region_states'))->toBeNull();
});

it('only accepts a connection state that is open', function () {
    Region::save(['CA', 'TX']);
    $base = ['name' => 'Ana Reyes', 'email' => 'ana@example.com', 'field_of_membership' => 'lives_in_region'];
    $v = \Illuminate\Support\Facades\Validator::make(['eligibility_state' => 'NY'], ['eligibility_state' => [\Illuminate\Validation\Rule::in(Region::codes())]]);
    expect($v->fails())->toBeTrue();
    expect(Region::codes())->toContain('TX');
});
