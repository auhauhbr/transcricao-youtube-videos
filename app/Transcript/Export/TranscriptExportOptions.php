<?php

namespace App\Transcript\Export;

use App\Enums\TranscriptExportFormat;
use App\Enums\TranscriptExportMode;

final readonly class TranscriptExportOptions
{
    public function __construct(
        public TranscriptExportFormat $format,
        public TranscriptExportMode $mode,
        public bool $timestamps,
    ) {}
}
