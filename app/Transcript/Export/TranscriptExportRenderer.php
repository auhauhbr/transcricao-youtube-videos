<?php

namespace App\Transcript\Export;

use App\Models\Transcript;

interface TranscriptExportRenderer
{
    /**
     * @param  iterable<array{startMs: int, text: string, chapterPosition: int|null}>  $items
     * @param  array<int, string>  $chapterTitles
     * @return iterable<string>
     */
    public function render(
        Transcript $transcript,
        iterable $items,
        array $chapterTitles,
        TranscriptExportOptions $options,
    ): iterable;
}
