<?php

namespace App\Transcript\Export;

use App\Models\Transcript;
use App\Models\Video;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class TranscriptDownloadResponse
{
    public function __construct(
        private TranscriptExporter $exporter,
        private TranscriptExportFilename $filename,
    ) {}

    public function make(Transcript $transcript, Video $video, TranscriptExportOptions $options): StreamedResponse
    {
        $transcript->setRelation('video', $video);

        return response()->streamDownload(
            function () use ($transcript, $options): void {
                foreach ($this->exporter->chunks($transcript, $options) as $chunk) {
                    echo $chunk;
                }
            },
            $this->filename->make($video, $options),
            [
                'Content-Type' => $options->format->contentType(),
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
