<?php

it('shows the public home page', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Golden West')
        ->assertSee('fictional institution')
        ->assertSee('Join free');
});
