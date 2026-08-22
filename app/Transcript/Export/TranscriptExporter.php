<?php

namespace App\Transcript\Export;

use App\Enums\TranscriptExportFormat;
use App\Enums\TranscriptExportMode;
use App\Models\Transcript;
use App\Transcript\TranscriptBlockBuilder;
use Generator;

final readonly class TranscriptExporter
{
    public function __construct(
        private TranscriptBlockBuilder $blockBuilder,
        private TxtTranscriptRenderer $txtRenderer,
        private MarkdownTranscriptRenderer $markdownRenderer,
    ) {}

    /** @return iterable<string> */
    public function chunks(Transcript $transcript, TranscriptExportOptions $options): iterable
    {
        $chapters = $transcript->chapters->map(fn ($chapter): array => [
            'position' => $chapter->position,
            'title' => $chapter->title,
            'startMs' => $chapter->start_ms,
            'endMs' => $chapter->end_ms,
        ])->all();
        $chapterTitles = [];

        foreach ($chapters as $chapter) {
            $chapterTitles[$chapter['position']] = $chapter['title'];
        }

        $renderer = match ($options->format) {
            TranscriptExportFormat::Txt => $this->txtRenderer,
            TranscriptExportFormat::Markdown => $this->markdownRenderer,
        };

        return $renderer->render(
            $transcript,
            $this->items($transcript, $chapters, $options),
            $chapterTitles,
            $options,
        );
    }

    /**
     * @param  list<array{position: int, title: string, startMs: int, endMs: int}>  $chapters
     * @return Generator<int, array{startMs: int, text: string, chapterPosition: int|null}>
     */
    private function items(Transcript $transcript, array $chapters, TranscriptExportOptions $options): Generator
    {
        if ($options->mode === TranscriptExportMode::Formatted) {
            $segments = $transcript->segments->map(fn ($segment): array => [
                'position' => $segment->position,
                'startMs' => $segment->start_ms,
                'endMs' => $segment->end_ms,
                'text' => $segment->text,
            ])->all();

            foreach ($this->blockBuilder->build($segments, $chapters) as $block) {
                yield [
                    'startMs' => $block['startMs'],
                    'text' => $block['text'],
                    'chapterPosition' => $block['chapterPosition'],
                ];
            }

            return;
        }

        $chapterIndex = 0;

        foreach ($transcript->segments as $segment) {
            while (isset($chapters[$chapterIndex]) && $segment->start_ms >= $chapters[$chapterIndex]['endMs']) {
                $chapterIndex++;
            }

            $chapter = $chapters[$chapterIndex] ?? null;
            $chapterPosition = $chapter !== null
                && $segment->start_ms >= $chapter['startMs']
                && $segment->start_ms < $chapter['endMs']
                    ? $chapter['position']
                    : null;

            yield [
                'startMs' => $segment->start_ms,
                'text' => $segment->text,
                'chapterPosition' => $chapterPosition,
            ];
        }
    }
}
