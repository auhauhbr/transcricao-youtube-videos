<?php

use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

test('the public home renders the landing page', function () {
    $this->withoutVite();

    $this->get('/')
        ->assertOk()
        ->assertSee('rel="icon"', false)
        ->assertSee(asset('favicon.png'), false)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->where('appName', config('app.name'))
        );

    expect(public_path('favicon.png'))->toBeFile();
});

test('the home route is read only', function () {
    expect(Route::getRoutes()->match(request()->create('/', 'GET'))->methods())
        ->toContain('GET')
        ->not->toContain('POST');

    $this->post('/')->assertMethodNotAllowed();
});

test('no transcript extraction endpoint exists', function () {
    $this->post('/transcripts/extract')->assertNotFound();
});
