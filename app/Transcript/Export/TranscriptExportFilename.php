<?php

namespace App\Transcript\Export;

use App\Models\Video;
use Illuminate\Support\Str;

final class TranscriptExportFilename
{
    private const MAX_SLUG_LENGTH = 100;

    public function make(Video $video, TranscriptExportOptions $options): string
    {
        $slug = Str::slug((string) $video->title);
        $slug = trim(mb_substr($slug, 0, self::MAX_SLUG_LENGTH), '-');

        if ($slug === '') {
            $safeVideoId = preg_replace('/[^A-Za-z0-9_-]/', '', $video->provider_video_id) ?: 'video';
            $slug = "youtube-{$safeVideoId}";
        }

        return "{$slug}.{$options->format->extension()}";
    }
}
