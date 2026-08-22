<?php

namespace App\Transcript\Data;

final readonly class TranscriptSegmentData
{
    public function __construct(
        public int $startMs,
        public int $endMs,
        public string $text,
    ) {}

    /**
     * @return array<string, int|string>
     */
    public function toArray(): array
    {
        return [
            'startMs' => $this->startMs,
            'endMs' => $this->endMs,
            'text' => $this->text,
        ];
    }
}
