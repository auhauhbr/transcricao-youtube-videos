<?php

use App\Actions\FailTranscriptExtraction;
use App\Actions\PersistTranscriptData;
use App\Enums\ExtractionErrorCode;
use App\Enums\ExtractionStatus;
use App\Jobs\ExtractTranscriptJob;
use App\Models\Extraction;
use App\Models\GuestUsage;
use App\Models\User;
use App\Models\Video;
use App\Transcript\Contracts\TranscriptProvider;
use App\Transcript\Data\TranscriptData;
use App\Transcript\Exceptions\TranscriptNotAvailableException;
use App\Transcript\Exceptions\TranscriptProviderTimeoutException;
use App\Transcript\Exceptions\VideoUnavailableException;
use App\Transcript\Providers\FakeTranscriptProvider;
use Illuminate\Cache\RateLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['cache.default' => 'array']);
    Queue::fake();
    $this->withCookie((string) config('transcripts.anonymous.cookie_name'), str_repeat('q', 43));

    $rateLimiter = app(RateLimiter::class);
    $rateLimiter->clear(md5('transcript-extractionsminute:127.0.0.1'));
    $rateLimiter->clear(md5('transcript-extractionshour:127.0.0.1'));
});

function youtubeQuotaUrls(): array
{
    return [
        'https://youtu.be/dQw4w9WgXcQ',
        'https://youtu.be/M7lc1UVf-VE',
        'https://youtu.be/9bZkp7q19f0',
        'https://youtu.be/BaW_jenozKc',
        'https://youtu.be/aqz-KE-bpKQ',
        'https://youtu.be/jNQXAC9IVRw',
    ];
}

test('a guest receives exactly three reservations and the fourth valid request creates nothing', function () {
    $this->get('/')->assertInertia(fn (Assert $page) => $page
        ->where('anonymousQuota', ['limit' => 3, 'used' => 0, 'remaining' => 3])
    );
    expect(GuestUsage::query()->count())->toBe(0);

    foreach (array_slice(youtubeQuotaUrls(), 0, 3) as $index => $url) {
        $this->post(route('transcripts.extract'), ['video_url' => $url])->assertRedirect();

        expect(GuestUsage::query()->count())->toBe(1)
            ->and(GuestUsage::query()->sole()->used_slots)->toBe($index + 1);
    }

    $this->get('/')->assertInertia(fn (Assert $page) => $page
        ->where('anonymousQuota', ['limit' => 3, 'used' => 3, 'remaining' => 0])
    );

    $fourthUrl = youtubeQuotaUrls()[3];
    $this->from('/')->post(route('transcripts.extract'), ['video_url' => $fourthUrl])
        ->assertRedirect('/')
        ->assertSessionHasInput('video_url', $fourthUrl)
        ->assertSessionHasErrors([
            'anonymous_quota' => 'Você utilizou suas 3 transcrições gratuitas. Entre ou crie uma conta para continuar.',
        ]);

    expect(Extraction::query()->count())->toBe(3)
        ->and(Video::query()->count())->toBe(3)
        ->and(GuestUsage::query()->sole()->used_slots)->toBe(3)
        ->and(Extraction::query()->whereNotNull('guest_usage_id')->count())->toBe(3);
    Queue::assertPushed(ExtractTranscriptJob::class, 3);
});

test('invalid URLs consume no anonymous slot and dispatch no work', function () {
    $this->get('/');

    $this->from('/')->post(route('transcripts.extract'), [
        'video_url' => 'https://youtube.com.evil.example/watch?v=dQw4w9WgXcQ',
    ])->assertSessionHasErrors('video_url');

    expect(GuestUsage::query()->count())->toBe(0)
        ->and(Extraction::query()->count())->toBe(0)
        ->and(Video::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

test('a rate limited request consumes no additional slot independently of product quota', function () {
    config(['transcripts.anonymous.limit' => 10]);

    foreach (array_slice(youtubeQuotaUrls(), 0, 5) as $url) {
        $this->post(route('transcripts.extract'), ['video_url' => $url])->assertRedirect();
    }

    $this->post(route('transcripts.extract'), ['video_url' => youtubeQuotaUrls()[5]])
        ->assertTooManyRequests();

    expect(GuestUsage::query()->sole()->used_slots)->toBe(5)
        ->and(Extraction::query()->count())->toBe(5);
    Queue::assertPushed(ExtractTranscriptJob::class, 5);
});

test('pending processing and ready guest extractions keep their reserved slot', function () {
    $this->post(route('transcripts.extract'), ['video_url' => youtubeQuotaUrls()[0]])->assertRedirect();
    $extraction = Extraction::query()->sole();
    $usage = GuestUsage::query()->sole();

    expect($extraction->status)->toBe(ExtractionStatus::Pending)
        ->and($usage->used_slots)->toBe(1);

    $extraction->markProcessing();
    expect($usage->refresh()->used_slots)->toBe(1);

    app(PersistTranscriptData::class)->handle($extraction, (new FakeTranscriptProvider)->fetch('dQw4w9WgXcQ'));

    expect($extraction->refresh()->status)->toBe(ExtractionStatus::Ready)
        ->and($usage->refresh()->used_slots)->toBe(1)
        ->and($extraction->guest_slot_released_at)->toBeNull();
});

test('terminal guest failures return one slot exactly once', function (Throwable $exception, ExtractionErrorCode $expectedCode) {
    $this->post(route('transcripts.extract'), ['video_url' => youtubeQuotaUrls()[0]])->assertRedirect();
    $extraction = Extraction::query()->sole();
    $usage = GuestUsage::query()->sole();
    $provider = new class($exception) implements TranscriptProvider
    {
        public function __construct(private readonly Throwable $exception) {}

        public function fetch(string $providerVideoId): TranscriptData
        {
            throw $this->exception;
        }
    };
    $job = new ExtractTranscriptJob($extraction->getKey());

    $job->handle($provider, app(PersistTranscriptData::class), app(FailTranscriptExtraction::class));

    expect($extraction->refresh()->status)->toBe(ExtractionStatus::Failed)
        ->and($extraction->error_code)->toBe($expectedCode)
        ->and($extraction->guest_slot_released_at)->not->toBeNull()
        ->and($usage->refresh()->used_slots)->toBe(0);

    app(FailTranscriptExtraction::class)->handle($extraction, $expectedCode, 'Repeated finalization.');
    $job->failed($exception);

    expect($usage->refresh()->used_slots)->toBe(0)
        ->and($extraction->refresh()->guest_slot_released_at)->not->toBeNull();
})->with([
    'transcript unavailable' => [new TranscriptNotAvailableException('internal'), ExtractionErrorCode::TranscriptNotAvailable],
    'video unavailable' => [new VideoUnavailableException('internal'), ExtractionErrorCode::VideoUnavailable],
]);

test('job retries do not reserve again and final exhaustion releases once', function () {
    $this->post(route('transcripts.extract'), ['video_url' => youtubeQuotaUrls()[0]])->assertRedirect();
    $extraction = Extraction::query()->sole();
    $usage = GuestUsage::query()->sole();
    $exception = new TranscriptProviderTimeoutException('internal');
    $provider = new class($exception) implements TranscriptProvider
    {
        public function __construct(private readonly Throwable $exception) {}

        public function fetch(string $providerVideoId): TranscriptData
        {
            throw $this->exception;
        }
    };
    $job = new ExtractTranscriptJob($extraction->getKey());

    foreach (range(1, 2) as $attempt) {
        expect(fn () => $job->handle($provider, app(PersistTranscriptData::class), app(FailTranscriptExtraction::class)))
            ->toThrow(TranscriptProviderTimeoutException::class);
        expect($usage->refresh()->used_slots)->toBe(1);
    }

    $job->failed($exception);
    $job->failed($exception);

    expect($usage->refresh()->used_slots)->toBe(0)
        ->and(Extraction::query()->count())->toBe(1)
        ->and($extraction->refresh()->guest_slot_released_at)->not->toBeNull();
});

test('authenticated users bypass product quota but remain valid extraction owners', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    foreach (array_slice(youtubeQuotaUrls(), 0, 5) as $url) {
        $this->post(route('transcripts.extract'), ['video_url' => $url])->assertRedirect();
    }

    expect(Extraction::query()->count())->toBe(5)
        ->and(Extraction::query()->where('user_id', $user->getKey())->count())->toBe(5)
        ->and(Extraction::query()->whereNotNull('guest_usage_id')->count())->toBe(0)
        ->and(GuestUsage::query()->count())->toBe(0);
    Queue::assertPushed(ExtractTranscriptJob::class, 5);
});
