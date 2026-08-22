<?php

namespace App\Transcript\YtDlp;

enum CaptionTrackKind: string
{
    case Manual = 'manual';
    case Automatic = 'automatic';
}
