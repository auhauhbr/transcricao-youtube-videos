<?php

use App\Enums\TranscriptExportFormat;
use App\Enums\TranscriptExportMode;
use App\Models\Video;
use App\Transcript\Export\TranscriptExportFilename;
use App\Transcript\Export\TranscriptExportOptions;

test('export filenames are safe slugs with a validated extension', function (?string $title, TranscriptExportFormat $format, string $expected) {
    $video = new Video([
        'provider_video_id' => 'dQw4w9WgXcQ',
        'title' => $title,
    ]);
    $options = new TranscriptExportOptions($format, TranscriptExportMode::Formatted, true);

    expect((new TranscriptExportFilename)->make($video, $options))->toBe($expected);
})->with([
    'accents and punctuation' => ['Vagas de emprego: bizarras!', TranscriptExportFormat::Txt, 'vagas-de-emprego-bizarras.txt'],
    'path separators' => ['../../Pasta\\Arquivo / final', TranscriptExportFormat::Markdown, 'pastaarquivo-final.md'],
    'emoji with text' => ['🎬 Olá, mundo!', TranscriptExportFormat::Txt, 'ola-mundo.txt'],
    'emoji only fallback' => ['🎬✨', TranscriptExportFormat::Markdown, 'youtube-dQw4w9WgXcQ.md'],
    'empty fallback' => ['', TranscriptExportFormat::Txt, 'youtube-dQw4w9WgXcQ.txt'],
    'null fallback' => [null, TranscriptExportFormat::Txt, 'youtube-dQw4w9WgXcQ.txt'],
]);
