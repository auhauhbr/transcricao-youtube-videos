<?php

use App\Transcript\Exceptions\TranscriptNotAvailableException;
use App\Transcript\Exceptions\TranscriptProviderException;
use App\Transcript\YtDlp\CaptionTrack;
use App\Transcript\YtDlp\CaptionTrackKind;
use App\Transcript\YtDlp\YtDlpProcessResult;
use Tests\Support\FakeYtDlpProcessRunner;

test('metadata command uses a canonical URL and the required safety flags', function () {
    $metadata = file_get_contents(__DIR__.'/../Fixtures/YtDlp/metadata-manual-chapters.json');
    $runner = new FakeYtDlpProcessRunner(
        fn (): YtDlpProcessResult => new YtDlpProcessResult(0, $metadata, ''),
    );

    $result = ytDlpGateway($runner, sys_get_temp_dir())->fetchMetadata('dQw4w9WgXcQ');
    $arguments = $runner->calls[0]['arguments'];

    expect($result['id'])->toBe('dQw4w9WgXcQ')
        ->and($arguments)->toContain('--ignore-config', '--no-plugin-dirs', '--no-playlist', '--skip-download', '--dump-single-json')
        ->and($arguments)->toContain('--js-runtimes', 'node')
        ->and($arguments)->not->toContain('--cookies', '--cookies-from-browser', '--exec')
        ->and($arguments[array_key_last($arguments)])->toBe('https://www.youtube.com/watch?v=dQw4w9WgXcQ');
});

test('invalid IDs and arbitrary URLs never reach the process runner', function (string $input) {
    $runner = new FakeYtDlpProcessRunner(
        fn (): YtDlpProcessResult => throw new RuntimeException('The runner must not be called.'),
    );

    expect(fn () => ytDlpGateway($runner, sys_get_temp_dir())->fetchMetadata($input))
        ->toThrow(TranscriptProviderException::class)
        ->and($runner->calls)->toBe([]);
})->with([
    'short ID' => ['abc'],
    'argument-like input' => ['--exec=whoami'],
    'external URL' => ['https://example.com'],
]);

test('an invalid language cannot inject an additional yt-dlp argument', function () {
    $runner = new FakeYtDlpProcessRunner(
        fn (): YtDlpProcessResult => throw new RuntimeException('The runner must not be called.'),
    );

    expect(fn () => ytDlpGateway($runner, sys_get_temp_dir())->fetchCaption(
        'dQw4w9WgXcQ',
        new CaptionTrack('en,--exec=whoami', 'Invalid', CaptionTrackKind::Manual),
    ))->toThrow(TranscriptProviderException::class)
        ->and($runner->calls)->toBe([]);
});

test('caption command writes only JSON3 in an isolated directory and always cleans it', function () {
    $temporaryPath = sys_get_temp_dir().'/yt-dlp-gateway-test-'.bin2hex(random_bytes(6));
    mkdir($temporaryPath, 0700);
    $fixture = file_get_contents(__DIR__.'/../Fixtures/YtDlp/caption-manual.json3');

    $runner = new FakeYtDlpProcessRunner(function (array $arguments) use ($fixture): YtDlpProcessResult {
        $outputIndex = array_search('--output', $arguments, true);
        expect($outputIndex)->not->toBeFalse();
        $directory = dirname($arguments[$outputIndex + 1]);
        file_put_contents($directory.'/caption.pt-BR.json3', $fixture);

        return new YtDlpProcessResult(0, '', '');
    });

    try {
        $caption = ytDlpGateway($runner, $temporaryPath)->fetchCaption(
            'dQw4w9WgXcQ',
            new CaptionTrack('pt-BR', 'Português', CaptionTrackKind::Manual),
        );
        $arguments = $runner->calls[0]['arguments'];

        expect($caption)->toBe($fixture)
            ->and($arguments)->toContain('--write-subs', '--sub-langs', 'pt-BR', '--sub-format', 'json3')
            ->and($arguments)->not->toContain('--write-auto-subs', '--cookies', '--cookies-from-browser', '--exec')
            ->and(glob($temporaryPath.'/transcript-*'))->toBe([]);
    } finally {
        rmdir($temporaryPath);
    }
});

test('automatic tracks use only the automatic subtitle flag', function () {
    $temporaryPath = sys_get_temp_dir().'/yt-dlp-gateway-test-'.bin2hex(random_bytes(6));
    mkdir($temporaryPath, 0700);
    $runner = new FakeYtDlpProcessRunner(function (array $arguments): YtDlpProcessResult {
        $outputIndex = array_search('--output', $arguments, true);
        file_put_contents(dirname($arguments[$outputIndex + 1]).'/caption.en-orig.json3', '{"events":[]}');

        return new YtDlpProcessResult(0, '', '');
    });

    try {
        ytDlpGateway($runner, $temporaryPath)->fetchCaption(
            'sCChyBh8x_M',
            new CaptionTrack('en-orig', 'English (Original)', CaptionTrackKind::Automatic),
        );

        expect($runner->calls[0]['arguments'])->toContain('--write-auto-subs')
            ->and($runner->calls[0]['arguments'])->not->toContain('--write-subs');
    } finally {
        rmdir($temporaryPath);
    }
});

test('exit code zero without a caption file is still transcript unavailable', function () {
    $runner = new FakeYtDlpProcessRunner(
        fn (): YtDlpProcessResult => new YtDlpProcessResult(0, '', 'There are no subtitles'),
    );

    expect(fn () => ytDlpGateway($runner, sys_get_temp_dir())->fetchCaption(
        'aqz-KE-bpKQ',
        new CaptionTrack('en', 'English', CaptionTrackKind::Manual),
    ))->toThrow(TranscriptNotAvailableException::class);
});
