<?php

namespace App\Transcript\Export;

use App\Enums\TranscriptExportMode;
use App\Models\Transcript;

final readonly class MarkdownTranscriptRenderer implements TranscriptExportRenderer
{
    public function __construct(private TranscriptTimestampFormatter $timestampFormatter) {}

    public function render(
        Transcript $transcript,
        iterable $items,
        array $chapterTitles,
        TranscriptExportOptions $options,
    ): iterable {
        $video = $transcript->video;

        yield '# '.$this->inline($video->title ?? 'Transcrição do YouTube')."\n\n";
        yield '- Canal: '.$this->inline($video->channel_name ?? 'Não informado')."\n";
        yield '- Idioma: '.$this->inline($transcript->language_name ?: $transcript->language_code)."\n";
        yield '- Origem: '.$transcript->source->publicLabel()."\n\n";

        $activeChapterPosition = null;
        $chapterWasSet = false;

        foreach ($items as $item) {
            if ($item['chapterPosition'] !== $activeChapterPosition || ! $chapterWasSet) {
                $activeChapterPosition = $item['chapterPosition'];
                $chapterWasSet = true;

                if ($activeChapterPosition !== null && isset($chapterTitles[$activeChapterPosition])) {
                    yield '## '.$this->inline($chapterTitles[$activeChapterPosition])."\n\n";
                }
            }

            $timestamp = $this->timestampFormatter->format($item['startMs']);

            if ($options->mode === TranscriptExportMode::Formatted) {
                if ($options->timestamps) {
                    yield "**{$timestamp}**\n\n";
                }

                yield $this->text($item['text'])."\n\n";

                continue;
            }

            yield ($options->timestamps ? "**{$timestamp}** " : '').$this->text($item['text'])."\n\n";
        }
    }

    private function inline(string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);

        return str_replace(
            ['\\', '`', '*', '_', '[', ']', '<', '>', '#', '|'],
            ['\\\\', '\\`', '\\*', '\\_', '\\[', '\\]', '\\<', '\\>', '\\#', '\\|'],
            $value,
        );
    }

    private function text(string $value): string
    {
        return str_replace(['\\', '<', '>'], ['\\\\', '\\<', '\\>'], $value);
    }
}
