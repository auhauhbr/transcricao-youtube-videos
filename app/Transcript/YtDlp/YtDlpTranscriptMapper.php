<?php

namespace App\Transcript\YtDlp;

use App\Enums\TranscriptSource;
use App\Enums\VideoProvider;
use App\Transcript\Data\ChapterData;
use App\Transcript\Data\TranscriptData;
use App\Transcript\Data\TranscriptSegmentData;
use App\Transcript\Data\VideoMetadataData;
use App\Transcript\Exceptions\TranscriptProviderException;

final class YtDlpTranscriptMapper
{
    /**
     * @param  array<string, mixed>  $metadata
     * @param  list<TranscriptSegmentData>  $segments
     */
    public function map(array $metadata, CaptionTrack $track, array $segments): TranscriptData
    {
        $providerVideoId = $this->requiredString($metadata, 'id');
        $title = $this->requiredString($metadata, 'title');
        $channelName = $this->firstString($metadata, ['channel', 'uploader']);
        $durationSeconds = $this->durationSeconds($metadata['duration'] ?? null);

        if ($channelName === null || $durationSeconds === null) {
            throw new TranscriptProviderException('yt-dlp returned incomplete video metadata.');
        }

        return new TranscriptData(
            video: new VideoMetadataData(
                provider: VideoProvider::YouTube,
                providerVideoId: $providerVideoId,
                title: $title,
                channelName: $channelName,
                durationSeconds: $durationSeconds,
                thumbnailUrl: $this->safeThumbnail($metadata['thumbnail'] ?? null),
            ),
            languageCode: $track->languageCode,
            languageName: $track->languageName,
            source: match ($track->kind) {
                CaptionTrackKind::Manual => TranscriptSource::Manual,
                CaptionTrackKind::Automatic => TranscriptSource::Automatic,
            },
            segments: $segments,
            chapters: $this->chapters($metadata['chapters'] ?? null, $durationSeconds),
        );
    }

    /**
     * @return list<ChapterData>
     */
    public function chapters(mixed $chapters, int $durationSeconds): array
    {
        if (! is_array($chapters)) {
            return [];
        }

        /** @var list<array{title: string, start: int, end: int|null}> $valid */
        $valid = [];

        foreach ($chapters as $chapter) {
            if (! is_array($chapter)) {
                continue;
            }

            $title = is_string($chapter['title'] ?? null) ? trim($chapter['title']) : '';
            $start = $this->secondsToMilliseconds($chapter['start_time'] ?? null);
            $end = $this->secondsToMilliseconds($chapter['end_time'] ?? null);

            if ($title === '' || $start === null) {
                continue;
            }

            $valid[] = ['title' => $title, 'start' => $start, 'end' => $end];
        }

        usort($valid, fn (array $left, array $right): int => $left['start'] <=> $right['start']);

        $durationMs = $durationSeconds * 1000;
        $result = [];

        foreach ($valid as $index => $chapter) {
            $nextStart = $valid[$index + 1]['start'] ?? null;
            $end = $chapter['end'] ?? $nextStart ?? $durationMs;

            if ($durationMs > $chapter['start']) {
                $end = min($end, $durationMs);
            }

            if ($end <= $chapter['start']) {
                continue;
            }

            $result[] = new ChapterData($chapter['title'], $chapter['start'], $end);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function requiredString(array $metadata, string $key): string
    {
        $value = is_string($metadata[$key] ?? null) ? trim($metadata[$key]) : '';

        if ($value === '') {
            throw new TranscriptProviderException("yt-dlp metadata is missing {$key}.");
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  list<string>  $keys
     */
    private function firstString(array $metadata, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (is_string($metadata[$key] ?? null) && trim($metadata[$key]) !== '') {
                return trim($metadata[$key]);
            }
        }

        return null;
    }

    private function durationSeconds(mixed $duration): ?int
    {
        if (! is_int($duration) && ! is_float($duration) && ! (is_string($duration) && is_numeric($duration))) {
            return null;
        }

        $seconds = (int) round((float) $duration);

        return $seconds > 0 ? $seconds : null;
    }

    private function secondsToMilliseconds(mixed $seconds): ?int
    {
        if (! is_int($seconds) && ! is_float($seconds) && ! (is_string($seconds) && is_numeric($seconds))) {
            return null;
        }

        $milliseconds = (int) round((float) $seconds * 1000);

        return $milliseconds >= 0 ? $milliseconds : null;
    }

    private function safeThumbnail(mixed $thumbnail): ?string
    {
        if (! is_string($thumbnail) || filter_var($thumbnail, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return strtolower((string) parse_url($thumbnail, PHP_URL_SCHEME)) === 'https' ? $thumbnail : null;
    }
}
