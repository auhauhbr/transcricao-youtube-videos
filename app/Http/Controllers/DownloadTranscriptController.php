<?php

namespace App\Http\Controllers;

use App\Enums\ExtractionStatus;
use App\Http\Requests\DownloadTranscriptRequest;
use App\Models\Extraction;
use App\Transcript\Export\TranscriptDownloadResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadTranscriptController extends Controller
{
    public function __invoke(
        DownloadTranscriptRequest $request,
        Extraction $extraction,
        TranscriptDownloadResponse $downloadResponse,
    ): StreamedResponse {
        abort_unless($extraction->status === ExtractionStatus::Ready, 409);

        $extraction->load(['video', 'transcript.segments', 'transcript.chapters']);
        $transcript = $extraction->transcript;

        if ($transcript === null) {
            Log::error('Ready extraction download requested without a transcript.', [
                'extraction_id' => $extraction->getKey(),
                'public_id' => $extraction->public_id,
            ]);

            abort(409);
        }

        return $downloadResponse->make($transcript, $extraction->video, $request->options());
    }
}
