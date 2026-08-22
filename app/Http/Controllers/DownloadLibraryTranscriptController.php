<?php

namespace App\Http\Controllers;

use App\Actions\FindUserTranscript;
use App\Http\Requests\DownloadTranscriptRequest;
use App\Transcript\Export\TranscriptDownloadResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadLibraryTranscriptController extends Controller
{
    public function __invoke(
        DownloadTranscriptRequest $request,
        string $userTranscript,
        FindUserTranscript $findUserTranscript,
        TranscriptDownloadResponse $downloadResponse,
    ): StreamedResponse {
        $item = $findUserTranscript->handle($request->user(), $userTranscript);
        $item->load(['transcript.video', 'transcript.segments', 'transcript.chapters']);
        $transcript = $item->transcript;

        return $downloadResponse->make($transcript, $transcript->video, $request->options());
    }
}
