<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows the single error as a red toast as well as beside the field', function () {
    seedReference();
    [$member, $checking] = verifiedMember();
    $this->actingAs($member)->from('/transfers/new')->post('/transfers', ['from_account_id' => $checking->id, 'to_account_id' => $checking->id, 'amount' => '5'])->assertRedirect('/transfers/new');
    $html = $this->actingAs($member)->get('/transfers/new')->getContent();
    expect($html)->toContain('class="toast bad"')->toContain('Choose two different accounts.');
});

it('sums up several field errors in one toast', function () {
    seedReference();
    [$member] = verifiedMember();
    $this->actingAs($member)->from('/support/new')->post('/support', [])->assertRedirect('/support/new');
    $html = $this->actingAs($member)->get('/support/new')->getContent();
    expect($html)->toContain('Please fix the highlighted fields.');
});

it('does not add a second toast when an error toast is already showing', function () {
    seedReference();
    [$member] = verifiedMember();
    $html = $this->actingAs($member)->withSession(['error' => 'Something went wrong.'])->get('/dashboard')->getContent();
    expect(substr_count($html, 'class="toast bad"'))->toBe(1);
});
