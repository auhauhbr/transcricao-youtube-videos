<?php

namespace App\Http\Controllers;

use App\Enums\ExtractionStatus;
use App\Http\Requests\DownloadTranscriptRequest;
use App\Models\Extraction;
use App\Transcript\Export\TranscriptExporter;
use App\Transcript\Export\TranscriptExportFilename;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadTranscriptController extends Controller
{
    public function __invoke(
        DownloadTranscriptRequest $request,
        Extraction $extraction,
        TranscriptExporter $exporter,
        TranscriptExportFilename $filename,
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

        $transcript->setRelation('video', $extraction->video);
        $options = $request->options();

        return response()->streamDownload(
            static function () use ($exporter, $transcript, $options): void {
                foreach ($exporter->chunks($transcript, $options) as $chunk) {
                    echo $chunk;
                }
            },
            $filename->make($extraction->video, $options),
            [
                'Content-Type' => $options->format->contentType(),
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
