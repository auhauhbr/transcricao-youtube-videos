<?php

namespace App\Enums;

enum TranscriptExportMode: string
{
    case Formatted = 'formatted';
    case Segmented = 'segmented';
}
