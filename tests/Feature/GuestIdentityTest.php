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
        ->and(GuestUsage::query()->sole()->token_hash)->toHaveLength(64)
        ->and($cookie->getValue())->not->toBe(GuestUsage::query()->sole()->token_hash);

    $response->assertInertia(fn (Assert $page) => $page
        ->missing('guestToken')
        ->missing('guest_token')
        ->missing('guestTokenHash')
        ->missing('guest_token_hash')
    );
});

test('the same valid token reuses one server-side guest identity across sessions', function () {
    $cookieName = (string) config('transcripts.anonymous.cookie_name');
    $this->withCookie($cookieName, str_repeat('r', 43));

    $this->get('/')->assertOk();
    $firstId = GuestUsage::query()->sole()->getKey();
    $this->flushSession();
    $this->get('/')->assertOk();

    expect(GuestUsage::query()->count())->toBe(1)
        ->and(GuestUsage::query()->sole()->getKey())->toBe($firstId);
});

test('a tampered cookie is replaced safely without exposing an error', function () {
    $cookieName = (string) config('transcripts.anonymous.cookie_name');

    $response = $this->withUnencryptedCookie($cookieName, 'tampered-cookie')->get('/');

    $response->assertOk()->assertCookie($cookieName);
    expect(GuestUsage::query()->count())->toBe(1)
        ->and(GuestUsage::query()->sole()->used_slots)->toBe(0);
});
