<?php

namespace App\Enums;

enum UserDocumentRevisionKind: string
{
    case Baseline = 'baseline';
    case Automatic = 'automatic';
    case Manual = 'manual';
    case RestoreBackup = 'restore_backup';
}
