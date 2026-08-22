<?php

namespace App\Transcript;

use App\Models\Transcript;

final readonly class TranscriptResultPresenter
{
    public function __construct(private TranscriptBlockBuilder $blockBuilder) {}

    /**
     * @return array{
     *   video: array{providerVideoId: string, title: string, channelName: string|null, channelId: string|null, thumbnailUrl: string|null, durationSeconds: int, youtubeUrl: string},
     *   transcript: array{
     *     languageCode: string,
     *     languageName: string|null,
     *     source: string,
     *     sourceLabel: string,
     *     wordCount: int,
     *     characterCount: int,
     *     blocks: list<array{position: int, startMs: int, endMs: int, text: string, chapterPosition: int|null}>,
     *     chapters: list<array{position: int, title: string, startMs: int, endMs: int}>
     *   }
     * }
     */
    public function present(Transcript $transcript): array
    {
        $transcript->loadMissing(['video', 'segments', 'chapters']);
        $video = $transcript->video;
        $segments = $transcript->segments->map(fn ($segment): array => [
            'position' => $segment->position,
            'startMs' => $segment->start_ms,
            'endMs' => $segment->end_ms,
            'text' => $segment->text,
        ])->all();
        $chapters = $transcript->chapters->map(fn ($chapter): array => [
            'position' => $chapter->position,
            'title' => $chapter->title,
            'startMs' => $chapter->start_ms,
            'endMs' => $chapter->end_ms,
        ])->all();

        return [
            'video' => [
                'providerVideoId' => $video->provider_video_id,
                'title' => $video->title ?? 'Transcrição do YouTube',
                'channelName' => $video->channel_name,
                'channelId' => $video->channel_id,
                'thumbnailUrl' => $video->thumbnail_url,
                'durationSeconds' => $video->duration_seconds ?? 0,
                'youtubeUrl' => "https://www.youtube.com/watch?v={$video->provider_video_id}",
            ],
            'transcript' => [
                'languageCode' => $transcript->language_code,
                'languageName' => $transcript->language_name,
                'source' => $transcript->source->value,
                'sourceLabel' => $transcript->source->publicLabel(),
                'wordCount' => $transcript->word_count,
                'characterCount' => $transcript->character_count,
                'blocks' => $this->blockBuilder->build($segments, $chapters),
                'chapters' => $chapters,
            ],
        ];
    }
}
