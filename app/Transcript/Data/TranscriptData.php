<?php

namespace App\Transcript\Data;

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
