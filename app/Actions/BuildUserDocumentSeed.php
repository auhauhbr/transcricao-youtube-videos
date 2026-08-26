<?php

namespace App\Actions;

use App\Models\Transcript;
use App\Transcript\TranscriptBlockBuilder;

final readonly class BuildUserDocumentSeed
{
    public function __construct(private TranscriptBlockBuilder $blockBuilder) {}

    /** @return array{title: string, content: array{type: string, content: list<array<string, mixed>>}} */
    public function handle(Transcript $transcript): array
    {
        $transcript->loadMissing(['video', 'segments', 'chapters']);
        $chapters = $transcript->chapters->map(fn ($chapter): array => [
            'position' => $chapter->position,
            'title' => $chapter->title,
            'startMs' => $chapter->start_ms,
            'endMs' => $chapter->end_ms,
        ])->all();
        $blocks = $this->blockBuilder->build(
            $transcript->segments->map(fn ($segment): array => [
                'position' => $segment->position,
                'startMs' => $segment->start_ms,
                'endMs' => $segment->end_ms,
                'text' => $segment->text,
            ])->all(),
            $chapters,
        );
        $chapterTitles = collect($chapters)->mapWithKeys(fn (array $chapter): array => [
            $chapter['position'] => $chapter['title'],
        ]);
        $content = [];
        $previousChapter = null;

        foreach ($blocks as $block) {
            $chapterPosition = $block['chapterPosition'];

            if ($chapterPosition !== null && $chapterPosition !== $previousChapter) {
                $content[] = [
                    'type' => 'heading',
                    'attrs' => ['level' => 2],
                    'content' => [['type' => 'text', 'text' => $chapterTitles->get($chapterPosition)]],
                ];
            }

            $content[] = [
                'type' => 'paragraph',
                'content' => [['type' => 'text', 'text' => $block['text']]],
            ];
            $previousChapter = $chapterPosition;
        }

        if ($content === []) {
            $content[] = ['type' => 'paragraph'];
        }

        return [
            'title' => $transcript->video->title ?? 'Transcrição do YouTube',
            'content' => ['type' => 'doc', 'content' => $content],
        ];
    }
}
