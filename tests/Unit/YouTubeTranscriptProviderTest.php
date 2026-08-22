<?php

use App\Transcript\Data\TranscriptData;
use App\Transcript\Providers\YouTubeTranscriptProvider;
use App\Transcript\YtDlp\CaptionTrackSelector;
use App\Transcript\YtDlp\Json3TranscriptParser;
use App\Transcript\YtDlp\YtDlpProcessResult;
use App\Transcript\YtDlp\YtDlpTranscriptMapper;
use Tests\Support\FakeYtDlpProcessRunner;

test('the real provider coordinates yt-dlp data into the existing DTO contract offline', function () {
    $temporaryPath = sys_get_temp_dir().'/youtube-provider-test-'.bin2hex(random_bytes(6));
    mkdir($temporaryPath, 0700);
    $metadata = file_get_contents(__DIR__.'/../Fixtures/YtDlp/metadata-manual-chapters.json');
    $caption = file_get_contents(__DIR__.'/../Fixtures/YtDlp/caption-manual.json3');
    $call = 0;

    $runner = new FakeYtDlpProcessRunner(function (array $arguments) use (&$call, $metadata, $caption): YtDlpProcessResult {
        $call++;

        if ($call === 1) {
            return new YtDlpProcessResult(0, $metadata, '');
        }

        $outputIndex = array_search('--output', $arguments, true);
        file_put_contents(dirname($arguments[$outputIndex + 1]).'/caption.pt-BR.json3', $caption);

        return new YtDlpProcessResult(0, '', '');
    });

    try {
        $provider = new YouTubeTranscriptProvider(
            gateway: ytDlpGateway($runner, $temporaryPath),
            trackSelector: new CaptionTrackSelector,
            parser: new Json3TranscriptParser(100),
            mapper: new YtDlpTranscriptMapper,
        );

        $transcript = $provider->fetch('dQw4w9WgXcQ');

        expect($transcript)->toBeInstanceOf(TranscriptData::class)
            ->and($transcript->video->providerVideoId)->toBe('dQw4w9WgXcQ')
            ->and($transcript->languageCode)->toBe('pt-BR')
            ->and($transcript->segments)->toHaveCount(3)
            ->and($transcript->chapters)->toHaveCount(3)
            ->and($runner->calls)->toHaveCount(2)
            ->and(glob($temporaryPath.'/transcript-*'))->toBe([]);
    } finally {
        rmdir($temporaryPath);
    }
});
