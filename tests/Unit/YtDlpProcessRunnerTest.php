<?php

use App\Transcript\Exceptions\TranscriptOutputLimitException;
use App\Transcript\Exceptions\TranscriptProviderTimeoutException;
use App\Transcript\YtDlp\YtDlpProcessRunner;

test('the process runner captures bounded output and exit status', function () {
    $runner = new YtDlpProcessRunner(PHP_BINARY, 2, 1, 1024);
    $result = $runner->run(['-r', 'fwrite(STDOUT, "metadata"); fwrite(STDERR, "notice");'], 1024);

    expect($result->successful())->toBeTrue()
        ->and($result->stdout)->toBe('metadata')
        ->and($result->stderr)->toBe('notice');
});

test('the process runner enforces stdout and timeout limits', function () {
    $outputRunner = new YtDlpProcessRunner(PHP_BINARY, 2, 1, 1024);
    $timeoutRunner = new YtDlpProcessRunner(PHP_BINARY, 0.05, 0.05, 1024);

    expect(fn () => $outputRunner->run(['-r', 'fwrite(STDOUT, str_repeat("x", 2048));'], 100))
        ->toThrow(TranscriptOutputLimitException::class)
        ->and(fn () => $timeoutRunner->run(['-r', 'usleep(500000);'], 100))
        ->toThrow(TranscriptProviderTimeoutException::class);
});
