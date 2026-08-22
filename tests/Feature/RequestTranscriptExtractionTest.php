<?php

use App\Actions\RequestTranscriptExtraction;
use App\Enums\ExtractionStatus;
use App\Enums\VideoProvider;
use App\Jobs\ExtractTranscriptJob;
use App\Models\GuestUsage;
use App\Models\Video;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Queue;

uses(DatabaseMigrations::class);

beforeEach(function () {
    config(['cache.default' => 'array']);
    Queue::fake();
});

function guestUsageForRequestAction(): GuestUsage
{
    return GuestUsage::query()->create([
        'token_hash' => hash('sha256', 'request-action-guest'),
    ]);
}

test('requesting extraction creates pending records and dispatches after commit', function () {
    $extraction = app(RequestTranscriptExtraction::class)->handle(
        VideoProvider::YouTube,
        'dQw4w9WgXcQ',
        guestUsage: guestUsageForRequestAction(),
    );

    expect($extraction->status)->toBe(ExtractionStatus::Pending)
        ->and($extraction->requested_language)->toBeNull()
        ->and($extraction->video->provider_video_id)->toBe('dQw4w9WgXcQ')
        ->and($extraction->transcript_id)->toBeNull();

    Queue::assertPushedOn('transcripts', ExtractTranscriptJob::class, fn (ExtractTranscriptJob $job): bool => $job->extractionId === $extraction->getKey() && $job->afterCommit === true
    );
});

test('requesting extraction reuses the canonical video without creating a transcript', function () {
    $video = Video::factory()->create([
        'provider' => VideoProvider::YouTube,
        'provider_video_id' => 'dQw4w9WgXcQ',
    ]);

    $guestUsage = guestUsageForRequestAction();
    $first = app(RequestTranscriptExtraction::class)->handle(VideoProvider::YouTube, 'dQw4w9WgXcQ', guestUsage: $guestUsage);
    $second = app(RequestTranscriptExtraction::class)->handle(VideoProvider::YouTube, 'dQw4w9WgXcQ', 'pt-BR', guestUsage: $guestUsage);

    expect(Video::query()->count())->toBe(1)
        ->and($first->video_id)->toBe($video->getKey())
        ->and($second->video_id)->toBe($video->getKey())
        ->and($second->requested_language)->toBe('pt-BR')
        ->and($video->transcripts()->count())->toBe(0);

    Queue::assertPushed(ExtractTranscriptJob::class, 2);
});

test('requesting extraction rejects an invalid provider video id before dispatch', function () {
    expect(fn () => app(RequestTranscriptExtraction::class)->handle(
        VideoProvider::YouTube,
        'https://example.com',
    ))->toThrow(InvalidArgumentException::class);

    expect(Video::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});
