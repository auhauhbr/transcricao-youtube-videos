<?php

namespace App\Http\Controllers;

use App\Actions\RequestTranscriptExtraction;
use App\Enums\VideoProvider;
use App\Http\Requests\ExtractTranscriptRequest;
use App\Support\YouTube\InvalidYouTubeUrlException;
use App\Support\YouTube\YouTubeUrlParser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class ExtractTranscriptController extends Controller
{
    public function __invoke(
        ExtractTranscriptRequest $request,
        YouTubeUrlParser $urlParser,
        RequestTranscriptExtraction $requestExtraction,
    ): RedirectResponse {
        try {
            $providerVideoId = $urlParser->parse($request->string('video_url')->toString());
        } catch (InvalidYouTubeUrlException) {
            throw ValidationException::withMessages([
                'video_url' => 'Informe uma URL válida de vídeo do YouTube.',
            ]);
        }

        $extraction = $requestExtraction->handle(
            VideoProvider::YouTube,
            $providerVideoId,
        );

        return to_route('extractions.show', $extraction);
    }
}
