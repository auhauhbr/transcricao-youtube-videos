<?php

namespace App\Actions;

use App\Enums\ChapterSource;
use App\Enums\ExtractionStatus;
use App\Models\Extraction;
use App\Models\Transcript;
use App\Transcript\Data\ChapterData;
use App\Transcript\Data\TranscriptData;
use App\Transcript\Data\TranscriptSegmentData;
use App\Transcript\Exceptions\TranscriptProviderException;
use Illuminate\Support\Facades\DB;

final class PersistTranscriptData
{
    private const INSERT_CHUNK_SIZE = 1000;

    public function __construct(private readonly EnsureUserTranscript $ensureUserTranscript) {}

    public function handle(Extraction $extraction, TranscriptData $data): Transcript
    {
        [$segmentRows, $wordCount, $characterCount] = $this->prepareSegments($data->segments);
        $chapterRows = $this->prepareChapters($data->chapters);
        $this->assertMatchingVideo($extraction, $data);

        return DB::transaction(function () use ($extraction, $data, $segmentRows, $chapterRows, $wordCount, $characterCount): Transcript {
            $lockedExtraction = Extraction::query()
                ->with('video')
                ->lockForUpdate()
                ->findOrFail($extraction->getKey());

            if ($lockedExtraction->status === ExtractionStatus::Ready && $lockedExtraction->transcript_id !== null) {
                $transcript = Transcript::query()->findOrFail($lockedExtraction->transcript_id);
                $this->ensureLibraryItem($lockedExtraction, $transcript);

                return $transcript;
            }

            if ($lockedExtraction->status !== ExtractionStatus::Processing) {
                throw new TranscriptProviderException('The extraction is not ready to persist transcript data.');
            }

            $metadata = [
                'title' => $data->video->title,
                'channel_name' => $data->video->channelName,
                'duration_seconds' => $data->video->durationSeconds,
            ];

            if ($data->video->thumbnailUrl !== null) {
                $metadata['thumbnail_url'] = $data->video->thumbnailUrl;
            }

            $lockedExtraction->video->forceFill($metadata)->save();

            $transcript = Transcript::query()->firstOrCreate(
                [
                    'video_id' => $lockedExtraction->video_id,
                    'language_code' => $data->languageCode,
                    'source' => $data->source,
                ],
                [
                    'language_name' => $data->languageName,
                    'word_count' => $wordCount,
                    'character_count' => $characterCount,
                    'extracted_at' => now(),
                ],
            );

            $transcript = Transcript::query()->lockForUpdate()->findOrFail($transcript->getKey());
            $transcript->forceFill([
                'language_name' => $data->languageName,
                'word_count' => $wordCount,
                'character_count' => $characterCount,
                'extracted_at' => now(),
            ])->save();

            $transcript->segments()->delete();
            $transcript->chapters()->delete();

            $timestamp = now();
            $segmentRows = array_map(fn (array $row): array => [
                ...$row,
                'transcript_id' => $transcript->getKey(),
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ], $segmentRows);
            $chapterRows = array_map(fn (array $row): array => [
                ...$row,
                'transcript_id' => $transcript->getKey(),
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ], $chapterRows);

            foreach (array_chunk($segmentRows, self::INSERT_CHUNK_SIZE) as $chunk) {
                DB::table('transcript_segments')->insert($chunk);
            }

            if ($chapterRows !== []) {
                DB::table('chapters')->insert($chapterRows);
            }

            $lockedExtraction->markReady($transcript);
            $this->ensureLibraryItem($lockedExtraction, $transcript);

            return $transcript;
        });
    }

    /**
     * @param  list<TranscriptSegmentData>  $segments
     * @return array{list<array{position: int, start_ms: int, end_ms: int, text: string}>, int, int}
     */
    private function prepareSegments(array $segments): array
    {
        if ($segments === []) {
            throw new TranscriptProviderException('The transcript contains no segments.');
        }

        $rows = [];
        $texts = [];
        $wordCount = 0;

        foreach ($segments as $position => $segment) {
            $text = trim($segment->text);

            if ($text === '' || $segment->startMs < 0 || $segment->endMs < $segment->startMs) {
                throw new TranscriptProviderException('The transcript contains an invalid segment.');
            }

            preg_match_all('/[\p{L}\p{N}]+(?:[\x{2019}\'-][\p{L}\p{N}]+)*/u', $text, $matches);
            $wordCount += count($matches[0]);
            $texts[] = $text;
            $rows[] = [
                'position' => $position,
                'start_ms' => $segment->startMs,
                'end_ms' => $segment->endMs,
                'text' => $text,
            ];
        }

        return [$rows, $wordCount, mb_strlen(implode("\n", $texts), 'UTF-8')];
    }

    /**
     * @param  list<ChapterData>  $chapters
     * @return list<array{position: int, title: string, start_ms: int, end_ms: int, source: string}>
     */
    private function prepareChapters(array $chapters): array
    {
        $rows = [];

        foreach ($chapters as $position => $chapter) {
            $title = trim($chapter->title);

            if ($title === '' || $chapter->startMs < 0 || $chapter->endMs < $chapter->startMs) {
                throw new TranscriptProviderException('The transcript contains an invalid chapter.');
            }

            $rows[] = [
                'position' => $position,
                'title' => $title,
                'start_ms' => $chapter->startMs,
                'end_ms' => $chapter->endMs,
                'source' => ChapterSource::Provider->value,
            ];
        }

        return $rows;
    }

    private function assertMatchingVideo(Extraction $extraction, TranscriptData $data): void
    {
        $video = $extraction->relationLoaded('video') ? $extraction->video : $extraction->video()->firstOrFail();

        if ($video->provider_video_id !== $data->video->providerVideoId) {
            throw new TranscriptProviderException('The provider returned metadata for a different video.');
        }
    }

    private function ensureLibraryItem(Extraction $extraction, Transcript $transcript): void
    {
        if ($extraction->user_id !== null) {
            $this->ensureUserTranscript->handle((int) $extraction->user_id, (int) $transcript->getKey());
        }
    }
}
