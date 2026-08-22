<?php

namespace App\Http\Controllers;

use App\Actions\RequestTranscriptExtraction;
use App\Enums\VideoProvider;
use App\Exceptions\AnonymousQuotaExceededException;
use App\Guest\GuestIdentity;
use App\Http\Middleware\EnsureGuestIdentity;
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

        try {
            $user = $request->user();
            $guestIdentity = $request->attributes->get(EnsureGuestIdentity::ATTRIBUTE);
            $extraction = $requestExtraction->handle(
                provider: VideoProvider::YouTube,
                providerVideoId: $providerVideoId,
                user: $user,
                guestTokenHash: $user === null && $guestIdentity instanceof GuestIdentity
                    ? $guestIdentity->tokenHash
                    : null,
            );
        } catch (AnonymousQuotaExceededException) {
            throw ValidationException::withMessages([
                'anonymous_quota' => 'Você utilizou suas 3 transcrições gratuitas. Entre ou crie uma conta para continuar.',
            ]);
        }

        return to_route('extractions.show', $extraction);
    }
}
