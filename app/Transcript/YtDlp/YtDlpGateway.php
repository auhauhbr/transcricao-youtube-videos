<?php

namespace App\Transcript\YtDlp;

use App\Transcript\Exceptions\TranscriptNotAvailableException;
use App\Transcript\Exceptions\TranscriptOutputLimitException;
use App\Transcript\Exceptions\TranscriptProviderException;
use JsonException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class YtDlpGateway
{
    public function __construct(
        private readonly YtDlpProcessRunnerContract $runner,
        private readonly YtDlpErrorClassifier $errorClassifier,
        private readonly string $jsRuntime,
        private readonly string $temporaryPath,
        private readonly int $maxMetadataBytes,
        private readonly int $maxProcessStdoutBytes,
        private readonly int $maxCaptionBytes,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function fetchMetadata(string $providerVideoId): array
    {
        $url = $this->canonicalUrl($providerVideoId);
        $result = $this->runner->run([
            ...$this->baseArguments(),
            '--dump-single-json',
            $url,
        ], $this->maxMetadataBytes);

        if (! $result->successful()) {
            throw $this->errorClassifier->classify($result);
        }

        try {
            $metadata = json_decode($result->stdout, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new TranscriptProviderException('yt-dlp returned malformed metadata.', previous: $exception);
        }

        if (! is_array($metadata)) {
            throw new TranscriptProviderException('yt-dlp returned an invalid metadata document.');
        }

        return $metadata;
    }

    public function fetchCaption(string $providerVideoId, CaptionTrack $track): string
    {
        $url = $this->canonicalUrl($providerVideoId);

        if (preg_match('/\A[A-Za-z0-9._-]{1,64}\z/', $track->languageCode) !== 1) {
            throw new TranscriptProviderException('The selected caption language is invalid.');
        }

        $directory = $this->createTemporaryDirectory();

        try {
            $result = $this->runner->run([
                ...$this->baseArguments(),
                $track->kind === CaptionTrackKind::Manual ? '--write-subs' : '--write-auto-subs',
                '--sub-langs',
                $track->languageCode,
                '--sub-format',
                'json3',
                '--output',
                $directory.'/caption.%(ext)s',
                $url,
            ], $this->maxProcessStdoutBytes);

            if (! $result->successful()) {
                throw $this->errorClassifier->classify($result);
            }

            $files = glob($directory.'/caption.*.json3');

            if ($files === false || count($files) !== 1 || ! is_file($files[0])) {
                throw new TranscriptNotAvailableException('yt-dlp did not produce the selected caption track.');
            }

            $size = filesize($files[0]);

            if ($size === false || $size > $this->maxCaptionBytes) {
                throw new TranscriptOutputLimitException('The caption document exceeded the configured size limit.');
            }

            $caption = file_get_contents($files[0]);

            if ($caption === false || trim($caption) === '') {
                throw new TranscriptNotAvailableException('yt-dlp produced an empty caption document.');
            }

            return $caption;
        } finally {
            $this->removeTemporaryDirectory($directory);
        }
    }

    public function canonicalUrl(string $providerVideoId): string
    {
        if (preg_match('/\A[A-Za-z0-9_-]{11}\z/', $providerVideoId) !== 1) {
            throw new TranscriptProviderException('The YouTube video identifier is invalid.');
        }

        return "https://www.youtube.com/watch?v={$providerVideoId}";
    }

    /**
     * @return list<string>
     */
    private function baseArguments(): array
    {
        return [
            '--ignore-config',
            '--no-plugin-dirs',
            '--no-playlist',
            '--skip-download',
            '--no-progress',
            '--js-runtimes',
            $this->jsRuntime,
        ];
    }

    private function createTemporaryDirectory(): string
    {
        $basePath = realpath($this->temporaryPath);

        if ($basePath === false || ! is_dir($basePath) || ! is_writable($basePath)) {
            throw new TranscriptProviderException('The transcript temporary path is unavailable.');
        }

        $directory = $basePath.'/transcript-'.bin2hex(random_bytes(16));

        if (! mkdir($directory, 0700)) {
            throw new TranscriptProviderException('The transcript temporary directory could not be created.');
        }

        return $directory;
    }

    private function removeTemporaryDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            if ($item->isDir() && ! $item->isLink()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        @rmdir($directory);
    }
}
