<?php

use App\Actions\FailTranscriptExtraction;
use App\Actions\PersistTranscriptData;
use App\Enums\ExtractionErrorCode;
use App\Enums\ExtractionStatus;
use App\Enums\TranscriptSource;
use App\Jobs\ExtractTranscriptJob;
use App\Models\Extraction;
use App\Models\Transcript;
use App\Models\Video;
use App\Transcript\Contracts\TranscriptProvider;
use App\Transcript\Data\TranscriptData;
use App\Transcript\Data\TranscriptSegmentData;
use App\Transcript\Exceptions\TranscriptNotAvailableException;
use App\Transcript\Exceptions\TranscriptOutputLimitException;
use App\Transcript\Exceptions\TranscriptProviderBlockedException;
use App\Transcript\Exceptions\TranscriptProviderException;
use App\Transcript\Exceptions\TranscriptProviderTimeoutException;
use App\Transcript\Exceptions\VideoUnavailableException;
use App\Transcript\Providers\FakeTranscriptProvider;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function pendingExtraction(string $videoId = 'dQw4w9WgXcQ'): Extraction
{
    return Extraction::query()->create([
        'video_id' => Video::factory()->create(['provider_video_id' => $videoId])->getKey(),
    ]);
}

test('the job persists a complete transcript and marks extraction ready', function () {
    $extraction = pendingExtraction();
    $job = new ExtractTranscriptJob($extraction->getKey());

    $job->handle(new FakeTranscriptProvider, app(PersistTranscriptData::class), app(FailTranscriptExtraction::class));
    $extraction->refresh();
    $transcript = $extraction->transcript;

    expect($job)->toBeInstanceOf(ShouldBeUnique::class)
        ->and($job->queue)->toBe('transcripts')
        ->and($job->uniqueId())->toBe((string) $extraction->getKey())
        ->and($job->backoff())->toBe([60, 300])
        ->and($extraction->status)->toBe(ExtractionStatus::Ready)
        ->and($transcript)->not->toBeNull()
        ->and($transcript->source)->toBe(TranscriptSource::Manual)
        ->and($transcript->segments)->toHaveCount(6)
        ->and($transcript->chapters)->toHaveCount(3)
        ->and($transcript->segments->pluck('position')->all())->toBe([0, 1, 2, 3, 4, 5])
        ->and($transcript->chapters->pluck('position')->all())->toBe([0, 1, 2])
        ->and($transcript->word_count)->toBeGreaterThan(0)
        ->and($transcript->character_count)->toBeGreaterThan($transcript->word_count)
        ->and($extraction->video->title)->toBe('Como transformar conteúdo em conhecimento')
        ->and($extraction->video->channel_name)->toBe('Canal demonstrativo')
        ->and($extraction->video->duration_seconds)->toBe(270);
});

test('a ready job is idempotent and does not call the provider again', function () {
    $extraction = pendingExtraction();
    $provider = new class implements TranscriptProvider
    {
        public int $calls = 0;

        public function fetch(string $providerVideoId): TranscriptData
        {
            $this->calls++;

            return (new FakeTranscriptProvider)->fetch($providerVideoId);
        }
    };
    $job = new ExtractTranscriptJob($extraction->getKey());

    $job->handle($provider, app(PersistTranscriptData::class), app(FailTranscriptExtraction::class));
    $job->handle($provider, app(PersistTranscriptData::class), app(FailTranscriptExtraction::class));

    expect($provider->calls)->toBe(1)
        ->and(Transcript::query()->count())->toBe(1)
        ->and($extraction->refresh()->transcript->segments()->count())->toBe(6);
});

test('persisting the same transcript replaces children without duplicates', function () {
    $first = pendingExtraction();
    $second = Extraction::query()->create(['video_id' => $first->video_id]);
    $provider = new FakeTranscriptProvider;
    $persister = app(PersistTranscriptData::class);

    (new ExtractTranscriptJob($first->getKey()))->handle($provider, $persister, app(FailTranscriptExtraction::class));
    (new ExtractTranscriptJob($second->getKey()))->handle($provider, $persister, app(FailTranscriptExtraction::class));

    expect(Transcript::query()->count())->toBe(1)
        ->and($first->refresh()->transcript_id)->toBe($second->refresh()->transcript_id)
        ->and($second->transcript->segments()->count())->toBe(6)
        ->and($second->transcript->chapters()->count())->toBe(3);
});

test('persistence counts Unicode text and accepts transcripts without chapters', function () {
    $extraction = pendingExtraction();
    $base = (new FakeTranscriptProvider)->fetch('dQw4w9WgXcQ');
    $data = new TranscriptData(
        video: $base->video,
        languageCode: 'ja',
        languageName: '日本語',
        source: TranscriptSource::Automatic,
        segments: [
            new TranscriptSegmentData(0, 1000, 'Olá mundo'),
            new TranscriptSegmentData(1000, 2000, '日本語 テスト'),
        ],
        chapters: [],
    );

    $extraction->markProcessing();
    $transcript = app(PersistTranscriptData::class)->handle($extraction, $data);

    expect($transcript->word_count)->toBe(4)
        ->and($transcript->character_count)->toBe(17)
        ->and($transcript->source)->toBe(TranscriptSource::Automatic)
        ->and($transcript->chapters()->count())->toBe(0)
        ->and($extraction->refresh()->status)->toBe(ExtractionStatus::Ready);
});

test('terminal provider failures immediately mark extraction failed', function (Throwable $exception, ExtractionErrorCode $code) {
    $extraction = pendingExtraction();
    $provider = new class($exception) implements TranscriptProvider
    {
        public function __construct(private readonly Throwable $exception) {}

        public function fetch(string $providerVideoId): TranscriptData
        {
            throw $this->exception;
        }
    };

    (new ExtractTranscriptJob($extraction->getKey()))->handle($provider, app(PersistTranscriptData::class), app(FailTranscriptExtraction::class));

    expect($extraction->refresh()->status)->toBe(ExtractionStatus::Failed)
        ->and($extraction->error_code)->toBe($code)
        ->and($extraction->error_message)->not->toContain('internal detail')
        ->and(Transcript::query()->count())->toBe(0);
})->with([
    'transcript unavailable' => [new TranscriptNotAvailableException('internal detail'), ExtractionErrorCode::TranscriptNotAvailable],
    'video unavailable' => [new VideoUnavailableException('internal detail'), ExtractionErrorCode::VideoUnavailable],
    'provider blocked' => [new TranscriptProviderBlockedException('internal detail'), ExtractionErrorCode::ProviderBlocked],
    'output limit' => [new TranscriptOutputLimitException('internal detail'), ExtractionErrorCode::OutputLimit],
]);

test('retryable provider failures remain processing until final failure', function (Throwable $exception, ExtractionErrorCode $code) {
    $extraction = pendingExtraction();
    $provider = new class($exception) implements TranscriptProvider
    {
        public function __construct(private readonly Throwable $exception) {}

        public function fetch(string $providerVideoId): TranscriptData
        {
            throw $this->exception;
        }
    };
    $job = new ExtractTranscriptJob($extraction->getKey());

    expect(fn () => $job->handle($provider, app(PersistTranscriptData::class), app(FailTranscriptExtraction::class)))
        ->toThrow($exception::class);

    expect($extraction->refresh()->status)->toBe(ExtractionStatus::Processing)
        ->and($extraction->error_code)->toBeNull();

    $job->failed($exception);

    expect($extraction->refresh()->status)->toBe(ExtractionStatus::Failed)
        ->and($extraction->error_code)->toBe($code)
        ->and($extraction->error_message)->not->toContain('internal detail');
})->with([
    'timeout' => [new TranscriptProviderTimeoutException('internal detail'), ExtractionErrorCode::ProviderTimeout],
    'generic provider error' => [new TranscriptProviderException('internal detail'), ExtractionErrorCode::ProviderError],
]);
