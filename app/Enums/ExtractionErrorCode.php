<?php

namespace App\Enums;

enum ExtractionErrorCode: string
{
    case TranscriptNotAvailable = 'transcript_not_available';
    case VideoUnavailable = 'video_unavailable';
    case ProviderBlocked = 'provider_blocked';
    case ProviderTimeout = 'provider_timeout';
    case OutputLimit = 'output_limit';
    case ProviderError = 'provider_error';
}
