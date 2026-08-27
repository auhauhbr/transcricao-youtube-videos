<?php

use App\Models\Extraction;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Queue;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;

uses(RefreshDatabase::class);

function socialiteUser(string $id, ?string $email, ?string $name = 'Pessoa Social'): SocialiteUser
{
    $user = Mockery::mock(SocialiteUser::class);
    $user->shouldReceive('getId')->andReturn($id);
    $user->shouldReceive('getEmail')->andReturn($email);
    $user->shouldReceive('getName')->andReturn($name);

    return $user;
}

function fakeSocialiteCallback(string $provider, SocialiteUser $user): void
{
    $driver = Mockery::mock();
    $driver->shouldReceive('user')->once()->andReturn($user);
    Socialite::shouldReceive('driver')->once()->with($provider)->andReturn($driver);
}

test('google redirect uses the configured socialite driver', function () {
    $driver = Mockery::mock();
    $driver->shouldReceive('redirect')->once()->andReturn(new RedirectResponse('https://accounts.google.test'));
    Socialite::shouldReceive('driver')->once()->with('google')->andReturn($driver);

    $this->get(route('auth.google.redirect'))->assertRedirect('https://accounts.google.test');
});

test('google callback creates a passwordless user and social account', function () {
    fakeSocialiteCallback('google', socialiteUser('google-1', 'GOOGLE@EXAMPLE.COM', ' Google User '));

    $this->get(route('auth.google.callback'))->assertRedirect(route('library.index'));

    $user = User::query()->sole();
    expect($user->email)->toBe('google@example.com')->and($user->password)->toBeNull()->and($user->email_verified_at)->not->toBeNull();
    expect(SocialAccount::query()->sole()->only(['provider', 'provider_user_id', 'user_id']))->toBe([
        'provider' => 'google', 'provider_user_id' => 'google-1', 'user_id' => $user->getKey(),
    ]);
    $this->assertAuthenticatedAs($user);
    $this->get(route('library.index'))->assertOk();
});

test('existing google and microsoft identities authenticate their linked users and repair legacy verification', function (string $provider) {
    $user = User::factory()->unverified()->create();
    SocialAccount::query()->create(['user_id' => $user->getKey(), 'provider' => $provider, 'provider_user_id' => "{$provider}-1"]);
    fakeSocialiteCallback($provider, socialiteUser("{$provider}-1", 'other@example.com'));

    $this->get(route("auth.{$provider}.callback"))->assertRedirect(route('library.index'));
    $this->assertAuthenticatedAs($user);
    expect($user->refresh()->email_verified_at)->not->toBeNull()
        ->and(User::query()->count())->toBe(1)
        ->and(SocialAccount::query()->count())->toBe(1);
})->with(['google', 'microsoft']);

test('an already verified google identity remains verified and accesses private routes', function () {
    $user = User::factory()->create();
    SocialAccount::query()->create(['user_id' => $user->getKey(), 'provider' => 'google', 'provider_user_id' => 'google-verified']);
    $verifiedAt = $user->email_verified_at;
    fakeSocialiteCallback('google', socialiteUser('google-verified', 'other@example.com'));

    $this->get(route('auth.google.callback'))->assertRedirect(route('library.index'));

    expect($user->refresh()->email_verified_at?->equalTo($verifiedAt))->toBeTrue()
        ->and(User::query()->count())->toBe(1)
        ->and(SocialAccount::query()->count())->toBe(1);
    $this->get(route('library.index'))->assertOk();
});

test('microsoft callback creates an account', function () {
    fakeSocialiteCallback('microsoft', socialiteUser('microsoft-1', 'microsoft@example.com'));

    $this->get(route('auth.microsoft.callback'))->assertRedirect(route('library.index'));
    expect(SocialAccount::query()->sole()->provider)->toBe('microsoft');
});

test('social callbacks reject missing email and never auto-link an existing local email', function () {
    fakeSocialiteCallback('google', socialiteUser('google-no-email', null));
    $this->get(route('auth.google.callback'))->assertRedirect(route('login'))->assertSessionHasErrors('social');
    expect(User::query()->count())->toBe(0);

    User::factory()->create(['email' => 'existing@example.com']);
    fakeSocialiteCallback('google', socialiteUser('google-collision', 'existing@example.com'));
    $this->get(route('auth.google.callback'))->assertRedirect(route('login'))->assertSessionHasErrors('social');
    $this->assertGuest();
    expect(SocialAccount::query()->count())->toBe(0);
});

test('social callback failure leaves the browser unauthenticated and password login rejects passwordless users', function () {
    $driver = Mockery::mock();
    $driver->shouldReceive('user')->once()->andThrow(new RuntimeException('provider failed'));
    Socialite::shouldReceive('driver')->once()->with('google')->andReturn($driver);
    $this->get(route('auth.google.callback'))->assertRedirect(route('login'))->assertSessionHasErrors('social');
    $this->assertGuest();

    User::factory()->create(['email' => 'social@example.com', 'password' => null]);
    $this->from(route('login'))->post(route('login'), ['email' => 'social@example.com', 'password' => 'anything'])
        ->assertRedirect(route('login'))->assertSessionHasErrors('email');
});

test('social login adopts extractions from the current guest browser', function () {
    Queue::fake();
    $token = str_repeat('s', 43);
    $this->withCookie((string) config('transcripts.anonymous.cookie_name'), $token)
        ->post(route('transcripts.extract'), ['video_url' => 'https://youtu.be/dQw4w9WgXcQ'])
        ->assertRedirect();
    $extraction = Extraction::query()->sole();
    fakeSocialiteCallback('google', socialiteUser('google-guest', 'guest@example.com'));

    $this->withCookie((string) config('transcripts.anonymous.cookie_name'), $token)
        ->get(route('auth.google.callback'))->assertRedirect(route('library.index'));

    expect($extraction->refresh()->user_id)->toBe(User::query()->sole()->getKey());
});

test('provider identity is unique in the database', function () {
    SocialAccount::query()->create(['user_id' => User::factory()->create()->getKey(), 'provider' => 'google', 'provider_user_id' => 'same-id']);

    expect(fn () => SocialAccount::query()->create(['user_id' => User::factory()->create()->getKey(), 'provider' => 'google', 'provider_user_id' => 'same-id']))
        ->toThrow(UniqueConstraintViolationException::class);
});
