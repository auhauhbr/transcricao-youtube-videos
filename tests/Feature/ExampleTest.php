<?php

use App\Models\GuestUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

test('the test suite boots the application in the testing environment', function () {
    expect(app()->environment())->toBe('testing');
});

test('the public home renders the landing page', function () {
    $this->withoutVite();

    $this->get('/')
        ->assertOk()
        ->assertSee('rel="icon"', false)
        ->assertSee(asset('favicon.png'), false)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->where('appName', config('app.name'))
            ->where('extractUrl', route('transcripts.extract', absolute: false))
            ->where('auth.user', null)
            ->where('anonymousQuota', ['limit' => 3, 'used' => 0, 'remaining' => 3])
        );

    expect(public_path('favicon.png'))->toBeFile();
    expect(GuestUsage::query()->count())->toBe(0);
});

test('the home route is read only', function () {
    expect(Route::getRoutes()->match(request()->create('/', 'GET'))->methods())
        ->toContain('GET')
        ->not->toContain('POST');

    $this->post('/')->assertMethodNotAllowed();
});
uses(RefreshDatabase::class);
