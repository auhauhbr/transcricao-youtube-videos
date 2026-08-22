<?php

use Inertia\Testing\AssertableInertia as Assert;

test('the application renders the phase zero page', function () {
    $this->withoutVite();

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Home'));
});
