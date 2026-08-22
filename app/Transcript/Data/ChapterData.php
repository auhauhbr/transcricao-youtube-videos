<?php

namespace App\Transcript\Data;

final readonly class ChapterData
{
    public function __construct(
        public string $title,
        public int $startMs,
        public int $endMs,
    ) {}

    /**
     * @return array<string, int|string>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'startMs' => $this->startMs,
            'endMs' => $this->endMs,
        ];
    }
}
