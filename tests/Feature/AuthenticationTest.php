<?php

use App\Actions\ClaimGuestExtractions;
use App\Actions\FailTranscriptExtraction;
use App\Actions\PersistTranscriptData;
use App\Jobs\ExtractTranscriptJob;
use App\Models\Extraction;
use App\Models\GuestUsage;
use App\Models\Transcript;
use App\Models\User;
use App\Models\UserTranscript;
use App\Models\Video;
use App\Transcript\Providers\FakeTranscriptProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['cache.default' => 'array']);
    Cache::flush();
    Queue::fake();
});

function authGuestToken(string $character = 'g'): string
{
    return str_repeat($character, 43);
}

function useAuthGuestBrowser($test, string $character = 'g'): void
{
    $test->withCookie((string) config('transcripts.anonymous.cookie_name'), authGuestToken($character));
}

test('registration validates creates hashes authenticates and adopts the current guest extraction without duplication', function () {
    useAuthGuestBrowser($this);
    $this->post(route('transcripts.extract'), [
        'video_url' => 'https://youtu.be/dQw4w9WgXcQ',
    ])->assertRedirect();

    $extraction = Extraction::query()->sole();
    (new ExtractTranscriptJob($extraction->getKey()))->handle(
        new FakeTranscriptProvider,
        app(PersistTranscriptData::class),
        app(FailTranscriptExtraction::class),
    );
    $extraction->refresh();
    $publicId = $extraction->public_id;
    $videoId = $extraction->video_id;
    $transcriptId = $extraction->transcript_id;

    $response = $this->post(route('register'), [
        'name' => '  Maria Visitante  ',
        'email' => 'MARIA@EXAMPLE.COM',
        'password' => 'password-seguro',
        'password_confirmation' => 'password-seguro',
    ]);

    $user = User::query()->sole();
    $response->assertRedirect(route('home'));
    $this->assertAuthenticatedAs($user);

    expect($user->email)->toBe('maria@example.com')
        ->and(Hash::check('password-seguro', $user->password))->toBeTrue()
        ->and($extraction->refresh()->user_id)->toBe($user->getKey())
        ->and($extraction->guest_usage_id)->not->toBeNull()
        ->and($extraction->public_id)->toBe($publicId)
        ->and($extraction->video_id)->toBe($videoId)
        ->and($extraction->transcript_id)->toBe($transcriptId)
        ->and(Extraction::query()->count())->toBe(1)
        ->and(Video::query()->count())->toBe(1)
        ->and(Transcript::query()->count())->toBe(1);
    expect(UserTranscript::query()->where('user_id', $user->getKey())->count())->toBe(1);
    Queue::assertPushed(ExtractTranscriptJob::class, 1);
});

test('registration rejects invalid data without authenticating', function () {
    useAuthGuestBrowser($this);

    $this->from('/register')->post(route('register'), [
        'name' => '',
        'email' => 'invalid',
        'password' => 'short',
        'password_confirmation' => 'different',
    ])->assertRedirect('/register')->assertSessionHasErrors(['name', 'email', 'password']);

    $this->assertGuest();
    expect(User::query()->count())->toBe(0);
});

test('registration without previous guest usage does not create an empty quota ledger', function () {
    useAuthGuestBrowser($this, 'n');

    $this->get(route('register'))->assertOk();
    $this->post(route('register'), [
        'name' => 'Nova Conta',
        'email' => 'nova-conta@example.com',
        'password' => 'password-seguro',
        'password_confirmation' => 'password-seguro',
    ])->assertRedirect(route('home'));

    $this->assertAuthenticated();
    expect(GuestUsage::query()->count())->toBe(0);
});

test('login regenerates authentication and adopts only extractions from the current guest token', function () {
    useAuthGuestBrowser($this, 'l');
    $this->post(route('transcripts.extract'), [
        'video_url' => 'https://youtu.be/dQw4w9WgXcQ',
    ])->assertRedirect();
    $extraction = Extraction::query()->sole();
    $publicId = $extraction->public_id;
    $user = User::factory()->create([
        'email' => 'login@example.com',
        'password' => 'password-seguro',
    ]);

    $this->post(route('login'), [
        'email' => ' LOGIN@EXAMPLE.COM ',
        'password' => 'password-seguro',
        'remember' => true,
    ])->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($user);
    expect($extraction->refresh()->user_id)->toBe($user->getKey())
        ->and($extraction->public_id)->toBe($publicId)
        ->and(Extraction::query()->count())->toBe(1);
    Queue::assertPushed(ExtractTranscriptJob::class, 1);
});

test('login without previous guest usage performs a no-op claim without creating a ledger', function () {
    $user = User::factory()->create([
        'email' => 'sem-uso@example.com',
        'password' => 'password-seguro',
    ]);
    useAuthGuestBrowser($this, 'u');

    $this->get(route('login'))->assertOk();
    $this->post(route('login'), [
        'email' => 'sem-uso@example.com',
        'password' => 'password-seguro',
    ])->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($user);
    expect(GuestUsage::query()->count())->toBe(0);
});

test('an ULID or a different guest token cannot claim another browser extraction', function () {
    $ownerUsage = GuestUsage::query()->create([
        'token_hash' => hash('sha256', authGuestToken('o')),
        'used_slots' => 1,
    ]);
    $extraction = new Extraction;
    $extraction->video()->associate(Video::factory()->create());
    $extraction->guestUsage()->associate($ownerUsage);
    $extraction->save();
    $user = User::factory()->create([
        'email' => 'other@example.com',
        'password' => 'password-seguro',
    ]);
    useAuthGuestBrowser($this, 'x');

    $this->post(route('login'), [
        'email' => 'other@example.com',
        'password' => 'password-seguro',
        'claim' => $extraction->public_id,
    ])->assertRedirect();

    $this->assertAuthenticatedAs($user);
    expect($extraction->refresh()->user_id)->toBeNull()
        ->and($extraction->guest_usage_id)->toBe($ownerUsage->getKey());
});

test('invalid login credentials use a generic message', function (string $email, string $password) {
    User::factory()->create([
        'email' => 'known@example.com',
        'password' => 'correct-password',
    ]);
    useAuthGuestBrowser($this);

    $this->from('/login')->post(route('login'), compact('email', 'password'))
        ->assertRedirect('/login')
        ->assertSessionHasErrors(['email' => 'As credenciais informadas são inválidas.']);
    $this->assertGuest();
})->with([
    'unknown email' => ['unknown@example.com', 'wrong-password'],
    'known email wrong password' => ['known@example.com', 'wrong-password'],
]);

test('login is throttled by normalized email and IP without sleeps', function () {
    useAuthGuestBrowser($this);

    foreach (range(1, 5) as $attempt) {
        $this->from('/login')->post(route('login'), [
            'email' => ' THROTTLE@EXAMPLE.COM ',
            'password' => 'wrong-password',
        ])->assertRedirect('/login');
    }

    $this->post(route('login'), [
        'email' => 'throttle@example.com',
        'password' => 'wrong-password',
    ])->assertTooManyRequests();
});

test('registration has a separate IP throttle', function () {
    useAuthGuestBrowser($this);

    foreach (range(1, 5) as $attempt) {
        $this->post(route('register'), [])->assertSessionHasErrors('name');
    }

    $this->post(route('register'), [])->assertTooManyRequests();
    expect(User::query()->count())->toBe(0);
});

test('logout is POST only invalidates authentication and preserves the guest quota for this browser', function () {
    useAuthGuestBrowser($this, 'p');
    $this->post(route('transcripts.extract'), [
        'video_url' => 'https://youtu.be/dQw4w9WgXcQ',
    ])->assertRedirect();
    $user = User::factory()->create();

    $this->actingAs($user);
    app(ClaimGuestExtractions::class)->handle($user, GuestUsage::query()->sole());
    $this->post(route('logout'))->assertRedirect(route('home'));

    $this->assertGuest();
    $this->get('/')->assertInertia(fn (Assert $page) => $page
        ->where('auth.user', null)
        ->where('anonymousQuota', ['limit' => 3, 'used' => 1, 'remaining' => 2])
    );
    $this->get('/logout')->assertMethodNotAllowed();
});

test('logout from a browser without guest usage does not create a quota ledger', function () {
    $user = User::factory()->create();
    useAuthGuestBrowser($this, 'z');

    $this->actingAs($user)->post(route('logout'))->assertRedirect(route('home'));
    $this->get('/')->assertOk();

    $this->assertGuest();
    expect(GuestUsage::query()->count())->toBe(0);
});

test('authenticated shared props omit anonymous quota and expose only public user fields', function () {
    $user = User::factory()->create([
        'name' => 'Conta Segura',
        'email' => 'conta@example.com',
    ]);

    $this->actingAs($user)->get('/')->assertInertia(fn (Assert $page) => $page
        ->where('auth.user', [
            'id' => $user->getKey(),
            'name' => 'Conta Segura',
            'email' => 'conta@example.com',
        ])
        ->where('anonymousQuota', null)
        ->missing('auth.user.password')
        ->missing('auth.user.remember_token')
    );
});

test('account is protected and allows profile and password updates', function () {
    $user = User::factory()->create([
        'email' => 'before@example.com',
        'password' => 'old-password',
    ]);

    $this->get(route('account.show'))->assertRedirect(route('login'));

    $this->actingAs($user)
        ->get(route('account.show'))
        ->assertInertia(fn (Assert $page) => $page->component('Account/Show'));

    $this->patch(route('account.profile'), [
        'name' => 'Nome Atualizado',
        'email' => 'AFTER@EXAMPLE.COM',
    ])->assertRedirect()->assertSessionHas('status', 'profile-updated');

    $this->put(route('account.password'), [
        'current_password' => 'old-password',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertRedirect()->assertSessionHas('status', 'password-updated');

    expect($user->refresh()->name)->toBe('Nome Atualizado')
        ->and($user->email)->toBe('after@example.com')
        ->and(Hash::check('new-password', $user->password))->toBeTrue();
});
