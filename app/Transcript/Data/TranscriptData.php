<?php

namespace App\Transcript\Data;

use App\Enums\TranscriptSource;

final readonly class TranscriptData
{
    /**
     * @param  list<TranscriptSegmentData>  $segments
     * @param  list<ChapterData>  $chapters
     */
    public function __construct(
        public VideoMetadataData $video,
        public string $languageCode,
        public string $languageName,
        public TranscriptSource $source,
        public array $segments,
        public array $chapters,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'video' => $this->video->toArray(),
            'languageCode' => $this->languageCode,
            'languageName' => $this->languageName,
            'source' => $this->source->value,
            'segments' => array_map(
                fn (TranscriptSegmentData $segment): array => $segment->toArray(),
                $this->segments,
            ),
            'chapters' => array_map(
                fn (ChapterData $chapter): array => $chapter->toArray(),
                $this->chapters,
            ),
        ];
    }
}
