<?php

use App\Models\GuestUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('a guest receives a durable secure-by-environment first-party identity cookie', function () {
    $response = $this->get('/')->assertOk();
    $cookieName = (string) config('transcripts.anonymous.cookie_name');
    $cookie = collect($response->headers->getCookies())->first(fn ($cookie): bool => $cookie->getName() === $cookieName);

    expect($cookie)->not->toBeNull()
        ->and($cookie->isHttpOnly())->toBeTrue()
        ->and($cookie->getSameSite())->toBe('lax')
        ->and($cookie->getPath())->toBe('/')
        ->and($cookie->isSecure())->toBeFalse()
        ->and($cookie->getValue())->not->toBeEmpty()
        ->and(GuestUsage::query()->count())->toBe(0);

    $response->assertInertia(fn (Assert $page) => $page
        ->missing('guestToken')
        ->missing('guest_token')
        ->missing('guestTokenHash')
        ->missing('guest_token_hash')
    );
});

test('the same valid token remains stateless until a quota reservation is needed', function () {
    $cookieName = (string) config('transcripts.anonymous.cookie_name');
    $this->withCookie($cookieName, str_repeat('r', 43));

    $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('anonymousQuota', ['limit' => 3, 'used' => 0, 'remaining' => 3])
    );
    $this->flushSession();
    $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('anonymousQuota', ['limit' => 3, 'used' => 0, 'remaining' => 3])
    );

    expect(GuestUsage::query()->count())->toBe(0);
});

test('a tampered cookie is replaced safely without exposing an error', function () {
    $cookieName = (string) config('transcripts.anonymous.cookie_name');

    $response = $this->withUnencryptedCookie($cookieName, 'tampered-cookie')->get('/');

    $response->assertOk()->assertCookie($cookieName);
    expect(GuestUsage::query()->count())->toBe(0);
});

test('public authentication pages do not persist an unused quota ledger', function () {
    $this->get('/')->assertOk();
    $this->get('/login')->assertOk();
    $this->get('/register')->assertOk();
    $this->get('/library')->assertRedirect(route('login'));

    expect(GuestUsage::query()->count())->toBe(0);
});
