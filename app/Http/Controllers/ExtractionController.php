<?php

namespace App\Http\Controllers;

use App\Enums\ExtractionErrorCode;
use App\Enums\ExtractionStatus;
use App\Models\Extraction;
use App\Models\Transcript;
use App\Transcript\TranscriptBlockBuilder;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class ExtractionController extends Controller
{
    public function __construct(private readonly TranscriptBlockBuilder $blockBuilder) {}

    public function __invoke(Extraction $extraction): Response
    {
        $extraction->load('video');

        if ($extraction->status === ExtractionStatus::Ready) {
            $extraction->load(['transcript.segments', 'transcript.chapters']);

            if ($extraction->transcript === null) {
                Log::error('Ready extraction has no transcript.', [
                    'extraction_id' => $extraction->getKey(),
                    'public_id' => $extraction->public_id,
                ]);

                return Inertia::render('Extractions/Show', [
                    'extraction' => $this->extractionData($extraction, ExtractionStatus::Failed),
                    'video' => $this->processingVideoData($extraction),
                    'failureMessage' => 'Não foi possível exibir esta transcrição. Tente novamente mais tarde.',
                ]);
            }

            return Inertia::render('Extractions/Show', [
                'extraction' => $this->extractionData($extraction),
                'video' => $this->readyVideoData($extraction),
                'transcript' => $this->transcriptData($extraction->transcript),
                'downloadUrl' => route('extractions.download', $extraction, absolute: false),
            ]);
        }

        $props = [
            'extraction' => $this->extractionData($extraction),
            'video' => $this->processingVideoData($extraction),
        ];

        if ($extraction->status === ExtractionStatus::Failed) {
            $props['failureMessage'] = $this->failureMessage($extraction->error_code);
        }

        return Inertia::render('Extractions/Show', $props);
    }

    /** @return array{publicId: string, status: string, createdAt: string, startedAt: string|null, completedAt: string|null} */
    private function extractionData(Extraction $extraction, ?ExtractionStatus $publicStatus = null): array
    {
        return [
            'publicId' => $extraction->public_id,
            'status' => ($publicStatus ?? $extraction->status)->value,
            'createdAt' => $extraction->created_at->toIso8601String(),
            'startedAt' => $extraction->started_at?->toIso8601String(),
            'completedAt' => $extraction->completed_at?->toIso8601String(),
        ];
    }

    /** @return array{providerVideoId: string, title: string|null} */
    private function processingVideoData(Extraction $extraction): array
    {
        return [
            'providerVideoId' => $extraction->video->provider_video_id,
            'title' => $extraction->video->title,
        ];
    }

    /** @return array{providerVideoId: string, title: string, channelName: string, channelId: string|null, thumbnailUrl: string|null, durationSeconds: int, youtubeUrl: string} */
    private function readyVideoData(Extraction $extraction): array
    {
        $video = $extraction->video;

        return [
            'providerVideoId' => $video->provider_video_id,
            'title' => $video->title ?? 'Transcrição do YouTube',
            'channelName' => $video->channel_name ?? 'Canal não informado',
            'channelId' => $video->channel_id,
            'thumbnailUrl' => $video->thumbnail_url,
            'durationSeconds' => $video->duration_seconds ?? 0,
            'youtubeUrl' => "https://www.youtube.com/watch?v={$video->provider_video_id}",
        ];
    }

    /**
     * @return array{
     *   languageCode: string,
     *   languageName: string|null,
     *   source: string,
     *   wordCount: int,
     *   characterCount: int,
     *   blocks: list<array{position: int, startMs: int, endMs: int, text: string, chapterPosition: int|null}>,
     *   chapters: list<array{position: int, title: string, startMs: int, endMs: int}>
     * }
     */
    private function transcriptData(Transcript $transcript): array
    {
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
            'languageCode' => $transcript->language_code,
            'languageName' => $transcript->language_name,
            'source' => $transcript->source->value,
            'wordCount' => $transcript->word_count,
            'characterCount' => $transcript->character_count,
            'blocks' => $this->blockBuilder->build($segments, $chapters),
            'chapters' => $chapters,
        ];
    }

    private function failureMessage(?ExtractionErrorCode $code): string
    {
        return match ($code) {
            ExtractionErrorCode::TranscriptNotAvailable => 'Este vídeo não possui uma transcrição disponível.',
            ExtractionErrorCode::VideoUnavailable => 'Este vídeo está indisponível.',
            ExtractionErrorCode::ProviderBlocked => 'Não foi possível acessar o YouTube neste momento. Tente novamente mais tarde.',
            ExtractionErrorCode::ProviderTimeout => 'A extração demorou mais que o esperado. Tente novamente.',
            ExtractionErrorCode::OutputLimit => 'A transcrição é grande demais para ser processada.',
            ExtractionErrorCode::ProviderError, null => 'Não foi possível obter a transcrição. Tente novamente mais tarde.',
        };
    }
}
