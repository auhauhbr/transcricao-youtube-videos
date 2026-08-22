<?php

use App\Enums\TranscriptSource;
use App\Enums\VideoProvider;
use App\Transcript\Data\TranscriptData;
use App\Transcript\Providers\FakeTranscriptProvider;

test('the fake provider returns deterministic structured transcript data', function () {
    $provider = new FakeTranscriptProvider;
    $firstResult = $provider->fetch('dQw4w9WgXcQ');
    $secondResult = $provider->fetch('dQw4w9WgXcQ');

    expect($firstResult)
        ->toBeInstanceOf(TranscriptData::class)
        ->and($firstResult->toArray())->toBe($secondResult->toArray())
        ->and($firstResult->video->provider)->toBe(VideoProvider::YouTube)
        ->and($firstResult->source)->toBe(TranscriptSource::Manual)
        ->and($firstResult->video->providerVideoId)->toBe('dQw4w9WgXcQ')
        ->and($firstResult->segments)->toHaveCount(6)
        ->and($firstResult->chapters)->toHaveCount(3);
});

test('the fake transcript segments are ordered and have valid intervals', function () {
    $segments = (new FakeTranscriptProvider)->fetch('dQw4w9WgXcQ')->segments;
    $startTimes = array_map(fn ($segment): int => $segment->startMs, $segments);
    $sortedStartTimes = $startTimes;
    sort($sortedStartTimes);

    expect($startTimes)->toBe($sortedStartTimes);

    foreach ($segments as $segment) {
        expect($segment->startMs)->toBeLessThan($segment->endMs);
    }
});

test('the fake chapters align with transcript segments and video duration', function () {
    $transcript = (new FakeTranscriptProvider)->fetch('dQw4w9WgXcQ');
    $segmentStartTimes = array_map(fn ($segment): int => $segment->startMs, $transcript->segments);

    foreach ($transcript->chapters as $chapter) {
        expect($segmentStartTimes)
            ->toContain($chapter->startMs)
            ->and($chapter->startMs)->toBeLessThan($chapter->endMs)
            ->and($chapter->endMs)->toBeLessThanOrEqual($transcript->video->durationSeconds * 1000);
    }
});
