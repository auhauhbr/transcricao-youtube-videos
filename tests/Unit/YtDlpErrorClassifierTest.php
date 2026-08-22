<?php

use App\Transcript\Exceptions\TranscriptProviderBlockedException;
use App\Transcript\Exceptions\TranscriptProviderException;
use App\Transcript\Exceptions\VideoUnavailableException;
use App\Transcript\YtDlp\YtDlpErrorClassifier;
use App\Transcript\YtDlp\YtDlpProcessResult;

test('yt-dlp errors are classified into useful provider failures', function (string $stderr, string $expectedException) {
    $exception = (new YtDlpErrorClassifier)->classify(new YtDlpProcessResult(1, '', $stderr));

    expect($exception)->toBeInstanceOf($expectedException);
})->with([
    'unavailable video' => [
        file_get_contents(__DIR__.'/../Fixtures/YtDlp/stderr-unavailable.txt'),
        VideoUnavailableException::class,
    ],
    'bot block' => ['ERROR: Sign in to confirm you’re not a bot', TranscriptProviderBlockedException::class],
    'rate limit' => ['ERROR: HTTP Error 429: Too Many Requests', TranscriptProviderBlockedException::class],
    'generic failure' => ['ERROR: unexpected extractor response', TranscriptProviderException::class],
]);
