<?php

namespace App\Transcript\YtDlp;

final readonly class CaptionTrack
{
    public function __construct(
        public string $languageCode,
        public string $languageName,
        public CaptionTrackKind $kind,
    ) {}
}
