<?php

use App\Transcript\Exceptions\TranscriptNotAvailableException;
use App\Transcript\Exceptions\VideoUnavailableException;
use App\Transcript\Providers\YouTubeTranscriptProvider;

beforeEach(function () {
    if (env('RUN_EXTERNAL_YOUTUBE_TESTS') !== '1') {
        $this->markTestSkipped('Set RUN_EXTERNAL_YOUTUBE_TESTS=1 and run this test in the queue worker.');
    }
});

test('a public video with manual captions and chapters is extracted', function () {
    $transcript = $this->app->make(YouTubeTranscriptProvider::class)->fetch('arj7oStGLkU');

    expect($transcript->segments)->not->toBeEmpty()
        ->and($transcript->chapters)->not->toBeEmpty()
        ->and($transcript->segments[0]->endMs)->toBeGreaterThan($transcript->segments[0]->startMs);
});

test('a public video with original ASR captions is extracted', function () {
    $transcript = $this->app->make(YouTubeTranscriptProvider::class)->fetch('sCChyBh8x_M');

    expect($transcript->segments)->not->toBeEmpty()
        ->and($transcript->languageCode)->toBeIn(['en', 'en-orig']);
});

test('a Short can use the same provider video ID flow', function () {
    $transcript = $this->app->make(YouTubeTranscriptProvider::class)->fetch('g1zbexho0zc');

    expect($transcript->segments)->not->toBeEmpty();
});

test('missing captions and unavailable videos are categorized', function (string $videoId, string $exception) {
    expect(fn () => $this->app->make(YouTubeTranscriptProvider::class)->fetch($videoId))
        ->toThrow($exception);
})->with([
    'no captions' => ['aqz-KE-bpKQ', TranscriptNotAvailableException::class],
    'unavailable' => ['BaW_jenozKc', VideoUnavailableException::class],
]);
