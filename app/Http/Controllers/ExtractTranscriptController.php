<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExtractTranscriptRequest;
use App\Support\YouTube\InvalidYouTubeUrlException;
use App\Support\YouTube\YouTubeUrlParser;
use App\Transcript\Contracts\TranscriptProvider;
use App\Transcript\Exceptions\TranscriptProviderException;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ExtractTranscriptController extends Controller
{
    public function __invoke(
        ExtractTranscriptRequest $request,
        YouTubeUrlParser $urlParser,
        TranscriptProvider $transcriptProvider,
    ): Response {
        try {
            $providerVideoId = $urlParser->parse($request->string('video_url')->toString());
        } catch (InvalidYouTubeUrlException) {
            throw ValidationException::withMessages([
                'video_url' => 'Informe uma URL válida de vídeo do YouTube.',
            ]);
        }

        try {
            $transcript = $transcriptProvider->fetch($providerVideoId);
        } catch (TranscriptProviderException) {
            throw ValidationException::withMessages([
                'video_url' => 'Não foi possível obter a transcrição deste vídeo.',
            ]);
        }

        return Inertia::render('Transcripts/Show', [
            'transcript' => $transcript->toArray(),
            'youtubeUrl' => "https://www.youtube.com/watch?v={$providerVideoId}",
        ]);
    }
}
