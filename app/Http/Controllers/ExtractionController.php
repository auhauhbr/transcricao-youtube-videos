<?php

namespace App\Http\Controllers;

use App\Enums\ExtractionErrorCode;
use App\Enums\ExtractionStatus;
use App\Models\Extraction;
use App\Models\UserTranscript;
use App\Transcript\TranscriptResultPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class ExtractionController extends Controller
{
    public function __construct(private readonly TranscriptResultPresenter $presenter) {}

    public function __invoke(Request $request, Extraction $extraction): Response
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

            $result = $this->presenter->present($extraction->transcript);
            $libraryUrl = null;

            if ($request->user() !== null) {
                $libraryItem = UserTranscript::query()
                    ->where('user_id', $request->user()->getKey())
                    ->where('transcript_id', $extraction->transcript->getKey())
                    ->first(['public_id']);
                $libraryUrl = $libraryItem === null
                    ? null
                    : route('library.show', $libraryItem->public_id, absolute: false);
            }

            return Inertia::render('Extractions/Show', [
                'extraction' => $this->extractionData($extraction),
                ...$result,
                'downloadUrl' => route('extractions.download', $extraction, absolute: false),
                'libraryUrl' => $libraryUrl,
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
