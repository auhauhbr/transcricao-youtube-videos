<?php

use App\Enums\TranscriptExportFormat;
use App\Enums\TranscriptExportMode;
use App\Enums\TranscriptSource;
use App\Models\Chapter;
use App\Models\Transcript;
use App\Models\TranscriptSegment;
use App\Models\Video;
use App\Transcript\Export\MarkdownTranscriptRenderer;
use App\Transcript\Export\TranscriptExporter;
use App\Transcript\Export\TranscriptExportOptions;
use App\Transcript\Export\TranscriptTimestampFormatter;
use App\Transcript\Export\TxtTranscriptRenderer;
use App\Transcript\TranscriptBlockBuilder;
use Illuminate\Database\Eloquent\Collection;

function transcriptExporterForTest(): TranscriptExporter
{
    $formatter = new TranscriptTimestampFormatter;

    return new TranscriptExporter(
        new TranscriptBlockBuilder,
        new TxtTranscriptRenderer($formatter),
        new MarkdownTranscriptRenderer($formatter),
    );
}

function transcriptForExportTest(TranscriptSource $source = TranscriptSource::Manual, bool $withChapters = true): Transcript
{
    $video = new Video([
        'provider_video_id' => 'dQw4w9WgXcQ',
        'title' => 'Ação & café',
        'channel_name' => 'Canal Português',
    ]);
    $transcript = new Transcript([
        'language_code' => 'pt-BR',
        'language_name' => 'Português',
        'source' => $source,
        'word_count' => 6,
        'character_count' => 44,
    ]);
    $transcript->setRelation('video', $video);
    $transcript->setRelation('segments', new Collection([
        new TranscriptSegment(['position' => 0, 'start_ms' => 0, 'end_ms' => 2_000, 'text' => 'Olá, mundo!']),
        new TranscriptSegment(['position' => 1, 'start_ms' => 2_000, 'end_ms' => 4_000, 'text' => 'Unicode: ação e café.']),
    ]));
    $transcript->setRelation('chapters', new Collection($withChapters ? [
        new Chapter(['position' => 0, 'title' => 'Introdução', 'start_ms' => 0, 'end_ms' => 4_000]),
    ] : []));

    return $transcript;
}

test('all format mode and timestamp combinations preserve content and structure', function (
    TranscriptExportFormat $format,
    TranscriptExportMode $mode,
    bool $timestamps,
) {
    $options = new TranscriptExportOptions($format, $mode, $timestamps);
    $content = implode('', [...transcriptExporterForTest()->chunks(transcriptForExportTest(), $options)]);

    expect($content)
        ->toContain('Ação & café')
        ->toContain('Canal Português')
        ->toContain('Português')
        ->toContain('Legendas manuais')
        ->and(substr_count($content, 'Olá, mundo!'))->toBe(1)
        ->and(substr_count($content, 'Unicode: ação e café.'))->toBe(1);

    if ($format === TranscriptExportFormat::Markdown) {
        expect($content)->toContain('# Ação & café')->toContain('## Introdução');
    } else {
        expect($content)->toContain("Introdução\n==========");
    }

    if ($mode === TranscriptExportMode::Formatted) {
        expect($content)->toContain('Olá, mundo! Unicode: ação e café.');
    } else {
        expect($content)->not->toContain('Olá, mundo! Unicode: ação e café.');
    }

    if ($timestamps) {
        expect($content)->toContain('00:00');

        if ($mode === TranscriptExportMode::Segmented) {
            expect($content)->toContain('00:02');
        }
    } else {
        expect($content)->not->toMatch('/\b00:0[02]\b/');
    }
})->with([
    'TXT formatted with timestamps' => [TranscriptExportFormat::Txt, TranscriptExportMode::Formatted, true],
    'TXT formatted without timestamps' => [TranscriptExportFormat::Txt, TranscriptExportMode::Formatted, false],
    'TXT segmented with timestamps' => [TranscriptExportFormat::Txt, TranscriptExportMode::Segmented, true],
    'TXT segmented without timestamps' => [TranscriptExportFormat::Txt, TranscriptExportMode::Segmented, false],
    'Markdown formatted with timestamps' => [TranscriptExportFormat::Markdown, TranscriptExportMode::Formatted, true],
    'Markdown formatted without timestamps' => [TranscriptExportFormat::Markdown, TranscriptExportMode::Formatted, false],
    'Markdown segmented with timestamps' => [TranscriptExportFormat::Markdown, TranscriptExportMode::Segmented, true],
    'Markdown segmented without timestamps' => [TranscriptExportFormat::Markdown, TranscriptExportMode::Segmented, false],
]);

test('exports do not invent chapter headings and expose a friendly automatic source label', function () {
    $options = new TranscriptExportOptions(TranscriptExportFormat::Markdown, TranscriptExportMode::Formatted, true);
    $content = implode('', [...transcriptExporterForTest()->chunks(
        transcriptForExportTest(TranscriptSource::Automatic, false),
        $options,
    )]);

    expect($content)
        ->toContain('Legendas automáticas')
        ->not->toContain('## Introdução')
        ->and(substr_count($content, "\n## "))->toBe(0);
});

test('segmented chapter association is ordered and keeps the final chapter content', function () {
    $transcript = transcriptForExportTest();
    $transcript->setRelation('segments', new Collection([
        new TranscriptSegment(['position' => 0, 'start_ms' => 0, 'end_ms' => 1_000, 'text' => 'Primeiro.']),
        new TranscriptSegment(['position' => 1, 'start_ms' => 1_000, 'end_ms' => 2_000, 'text' => 'Segundo.']),
        new TranscriptSegment(['position' => 2, 'start_ms' => 3_999, 'end_ms' => 4_000, 'text' => 'Último.']),
    ]));
    $transcript->setRelation('chapters', new Collection([
        new Chapter(['position' => 0, 'title' => 'Um', 'start_ms' => 0, 'end_ms' => 1_000]),
        new Chapter(['position' => 1, 'title' => 'Dois', 'start_ms' => 1_000, 'end_ms' => 4_000]),
    ]));
    $options = new TranscriptExportOptions(TranscriptExportFormat::Txt, TranscriptExportMode::Segmented, false);
    $content = implode('', [...transcriptExporterForTest()->chunks($transcript, $options)]);

    expect($content)
        ->toMatch('/Um\n={2,}\n\nPrimeiro\.\nDois\n={4,}\n\nSegundo\.\nÚltimo\./s')
        ->and(substr_count($content, 'Último.'))->toBe(1);
});

test('Markdown keeps transcript markup as text instead of arbitrary HTML', function () {
    $transcript = transcriptForExportTest();
    $transcript->setRelation('segments', new Collection([
        new TranscriptSegment([
            'position' => 0,
            'start_ms' => 0,
            'end_ms' => 1_000,
            'text' => '<script>alert("não executar")</script> >> fala',
        ]),
    ]));
    $options = new TranscriptExportOptions(TranscriptExportFormat::Markdown, TranscriptExportMode::Segmented, false);
    $content = implode('', [...transcriptExporterForTest()->chunks($transcript, $options)]);

    expect($content)
        ->not->toContain('<script>')
        ->toContain('\\<script\\>alert("não executar")\\</script\\> \\>\\> fala');
});
