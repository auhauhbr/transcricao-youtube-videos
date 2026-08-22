<?php

namespace App\Enums;

enum TranscriptSource: string
{
    case Manual = 'manual';
    case Automatic = 'automatic';

    public function publicLabel(): string
    {
        return match ($this) {
            self::Manual => 'Legendas manuais',
            self::Automatic => 'Legendas automáticas',
        };
    }
}
