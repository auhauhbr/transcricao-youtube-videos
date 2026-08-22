<?php

namespace App\Enums;

enum TranscriptExportFormat: string
{
    case Txt = 'txt';
    case Markdown = 'md';

    public function extension(): string
    {
        return $this->value;
    }

    public function contentType(): string
    {
        return match ($this) {
            self::Txt => 'text/plain; charset=UTF-8',
            self::Markdown => 'text/markdown; charset=UTF-8',
        };
    }
}
