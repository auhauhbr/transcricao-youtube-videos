<?php

use App\Enums\ChapterSource;
use App\Enums\ExtractionErrorCode;
use App\Enums\ExtractionStatus;
use App\Enums\TranscriptSource;
use App\Models\Chapter;
use App\Models\Extraction;
use App\Models\Transcript;
use App\Models\TranscriptSegment;
use App\Models\Video;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

test('an extraction receives an opaque public id and expected casts', function () {
    $extraction = Extraction::query()->create([
        'video_id' => Video::factory()->create()->getKey(),
    ])->refresh();

    expect($extraction->public_id)->toHaveLength(26)
        ->and($extraction->status)->toBe(ExtractionStatus::Pending)
        ->and($extraction->started_at)->toBeNull()
        ->and($extraction->completed_at)->toBeNull();
});

test('public extraction ids are unique in the database', function () {
    $video = Video::factory()->create();
    $publicId = '01K37XW6ZA8XZPB4XWYSP8ZKXY';

    Extraction::query()->forceCreate([
        'public_id' => $publicId,
        'video_id' => $video->getKey(),
        'status' => ExtractionStatus::Pending,
    ]);

    expect(fn () => Extraction::query()->forceCreate([
        'public_id' => $publicId,
        'video_id' => $video->getKey(),
        'status' => ExtractionStatus::Pending,
    ]))->toThrow(QueryException::class);
});

test('an extraction follows valid state transitions and links its result', function () {
    $video = Video::factory()->create();
    $extraction = Extraction::query()->create(['video_id' => $video->getKey()]);
    $transcript = Transcript::query()->create([
        'video_id' => $video->getKey(),
        'language_code' => 'pt-BR',
        'language_name' => 'Português',
        'source' => TranscriptSource::Manual,
        'word_count' => 2,
        'character_count' => 11,
        'extracted_at' => now(),
    ]);

    $extraction->markProcessing();

    expect($extraction->status)->toBe(ExtractionStatus::Processing)
        ->and($extraction->started_at)->toBeInstanceOf(Carbon::class);

    $extraction->markReady($transcript);

    expect($extraction->status)->toBe(ExtractionStatus::Ready)
        ->and($extraction->completed_at)->toBeInstanceOf(Carbon::class)
        ->and($extraction->transcript->is($transcript))->toBeTrue()
        ->and($extraction->video->is($video))->toBeTrue();
});

test('an extraction records a stable failure code', function () {
    $extraction = Extraction::query()->create([
        'video_id' => Video::factory()->create()->getKey(),
    ]);

    $extraction->markProcessing();
    $extraction->markFailed(ExtractionErrorCode::TranscriptNotAvailable, 'Safe message.');

    expect($extraction->status)->toBe(ExtractionStatus::Failed)
        ->and($extraction->error_code)->toBe(ExtractionErrorCode::TranscriptNotAvailable)
        ->and($extraction->error_message)->toBe('Safe message.')
        ->and($extraction->completed_at)->toBeInstanceOf(Carbon::class);
});

test('invalid extraction transitions are rejected', function () {
    $extraction = Extraction::query()->create([
        'video_id' => Video::factory()->create()->getKey(),
    ]);

    expect(fn () => $extraction->markReady(new Transcript))->toThrow(LogicException::class);

    $extraction->markProcessing();
    $extraction->markFailed(ExtractionErrorCode::ProviderError, 'Safe message.');

    expect(fn () => $extraction->markProcessing())->toThrow(LogicException::class);
});

test('transcripts cast source and expose ordered optional children', function () {
    $video = Video::factory()->create();
    $transcript = Transcript::query()->create([
        'video_id' => $video->getKey(),
        'language_code' => 'ja',
        'language_name' => '日本語',
        'source' => TranscriptSource::Automatic,
        'word_count' => 2,
        'character_count' => 5,
        'extracted_at' => now(),
    ])->refresh();

    TranscriptSegment::query()->create([
        'transcript_id' => $transcript->getKey(),
        'position' => 1,
        'start_ms' => 1000,
        'end_ms' => 2000,
        'text' => 'Segundo',
    ]);
    TranscriptSegment::query()->create([
        'transcript_id' => $transcript->getKey(),
        'position' => 0,
        'start_ms' => 0,
        'end_ms' => 1000,
        'text' => 'Primeiro',
    ]);

    expect($transcript->source)->toBe(TranscriptSource::Automatic)
        ->and($transcript->video->is($video))->toBeTrue()
        ->and($transcript->segments->pluck('position')->all())->toBe([0, 1])
        ->and($transcript->chapters)->toHaveCount(0);
});

test('database uniqueness protects transcript identity', function () {
    $video = Video::factory()->create();
    $attributes = [
        'video_id' => $video->getKey(),
        'language_code' => 'pt-BR',
        'language_name' => 'Português',
        'source' => TranscriptSource::Manual,
        'word_count' => 1,
        'character_count' => 5,
        'extracted_at' => now(),
    ];
    Transcript::query()->create($attributes);

    expect(fn () => Transcript::query()->create($attributes))->toThrow(QueryException::class);
});

test('database uniqueness protects segment positions', function () {
    $video = Video::factory()->create();
    $transcript = Transcript::query()->create([
        'video_id' => $video->getKey(),
        'language_code' => 'pt-BR',
        'language_name' => 'Português',
        'source' => TranscriptSource::Manual,
        'word_count' => 1,
        'character_count' => 5,
        'extracted_at' => now(),
    ]);

    TranscriptSegment::query()->create([
        'transcript_id' => $transcript->getKey(),
        'position' => 0,
        'start_ms' => 0,
        'end_ms' => 1000,
        'text' => 'Texto',
    ]);
    expect(fn () => TranscriptSegment::query()->create([
        'transcript_id' => $transcript->getKey(),
        'position' => 0,
        'start_ms' => 1000,
        'end_ms' => 2000,
        'text' => 'Duplicado',
    ]))->toThrow(QueryException::class);
});

test('database uniqueness protects chapter positions', function () {
    $video = Video::factory()->create();
    $transcript = Transcript::query()->create([
        'video_id' => $video->getKey(),
        'language_code' => 'pt-BR',
        'language_name' => 'Português',
        'source' => TranscriptSource::Manual,
        'word_count' => 1,
        'character_count' => 5,
        'extracted_at' => now(),
    ]);

    Chapter::query()->create([
        'transcript_id' => $transcript->getKey(),
        'position' => 0,
        'title' => 'Início',
        'start_ms' => 0,
        'end_ms' => 1000,
        'source' => ChapterSource::Provider,
    ]);
    expect(fn () => Chapter::query()->create([
        'transcript_id' => $transcript->getKey(),
        'position' => 0,
        'title' => 'Duplicado',
        'start_ms' => 0,
        'end_ms' => 1000,
        'source' => ChapterSource::Provider,
    ]))->toThrow(QueryException::class);
});
