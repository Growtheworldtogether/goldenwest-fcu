<?php

it('shows the public home page with consistent branding', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Golden West Federal Credit Union')
        ->assertSee('Member-owned since 1958')
        ->assertSee('Open an account');
});

it('keeps project wording out of the visible page', function () {
    $html = strip_tags($this->get('/')->getContent());
    $body = preg_replace('/Golden West Federal Credit Union is an independent portfolio project[^.]*\.[^.]*\./', '', $html);

    foreach (['fictional', 'demo', 'placeholder', 'sample', 'lorem'] as $word) {
        expect(strtolower($body))->not->toContain($word);
    }
});
