<?php

namespace App\Transcript\Export;

final class TranscriptTimestampFormatter
{
    public function format(int $milliseconds): string
    {
        $totalSeconds = intdiv(max(0, $milliseconds), 1_000);
        $hours = intdiv($totalSeconds, 3_600);
        $minutes = intdiv($totalSeconds % 3_600, 60);
        $seconds = $totalSeconds % 60;

        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        }

        return sprintf('%02d:%02d', $minutes, $seconds);
    }
}
