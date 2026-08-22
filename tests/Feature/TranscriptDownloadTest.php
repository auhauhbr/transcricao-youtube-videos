<?php

use App\Enums\ChapterSource;
use App\Enums\ExtractionErrorCode;
use App\Enums\ExtractionStatus;
use App\Enums\TranscriptSource;
use App\Models\Chapter;
use App\Models\Extraction;
use App\Models\Transcript;
use App\Models\TranscriptSegment;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

function readyExtractionForDownload(?string $title = 'Título seguro / com acentos 🎬'): Extraction
{
    $video = Video::factory()->create([
        'provider_video_id' => 'dQw4w9WgXcQ',
        'title' => $title,
        'channel_name' => 'Canal seguro',
        'duration_seconds' => 3_605,
    ]);
    $transcript = Transcript::query()->create([
        'video_id' => $video->getKey(),
        'language_code' => 'pt-BR',
        'language_name' => 'Português',
        'source' => TranscriptSource::Manual,
        'word_count' => 5,
        'character_count' => 37,
        'extracted_at' => now(),
    ]);

    foreach ([
        ['position' => 0, 'start_ms' => 0, 'end_ms' => 2_000, 'text' => 'Primeira ação.'],
        ['position' => 1, 'start_ms' => 2_000, 'end_ms' => 4_000, 'text' => 'Segundo café.'],
        ['position' => 2, 'start_ms' => 3_603_000, 'end_ms' => 3_604_000, 'text' => 'Conteúdo final.'],
    ] as $segment) {
        TranscriptSegment::query()->create(['transcript_id' => $transcript->getKey(), ...$segment]);
    }

    foreach ([
        ['position' => 0, 'title' => 'Introdução', 'start_ms' => 0, 'end_ms' => 4_000],
        ['position' => 1, 'title' => 'Encerramento', 'start_ms' => 4_000, 'end_ms' => 3_605_000],
    ] as $chapter) {
        Chapter::query()->create([
            'transcript_id' => $transcript->getKey(),
            'source' => ChapterSource::Provider,
            ...$chapter,
        ]);
    }

    $extraction = Extraction::query()->create(['video_id' => $video->getKey()]);
    $extraction->markProcessing();
    $extraction->markReady($transcript);

    return $extraction;
}

test('all validated download combinations return a local attachment with expected content', function (
    string $format,
    string $mode,
    bool $timestamps,
) {
    Queue::fake();
    $extraction = readyExtractionForDownload();
    $response = $this->get(route('extractions.download', [
        'extraction' => $extraction,
        'format' => $format,
        'mode' => $mode,
        'timestamps' => $timestamps ? '1' : '0',
    ]));

    $response->assertOk();

    expect($response->headers->get('Content-Disposition'))
        ->toContain('attachment')
        ->toContain("titulo-seguro-com-acentos.{$format}")
        ->and($response->headers->get('Content-Type'))->toBe(
            $format === 'txt' ? 'text/plain; charset=UTF-8' : 'text/markdown; charset=UTF-8',
        );

    $content = $response->streamedContent();

    expect($content)
        ->toContain('Primeira ação.')
        ->toContain('Segundo café.')
        ->toContain('Conteúdo final.')
        ->toContain('Introdução')
        ->toContain('Encerramento')
        ->and(substr_count($content, 'Primeira ação.'))->toBe(1);

    if ($mode === 'formatted') {
        expect($content)->toContain('Primeira ação. Segundo café.');
    } else {
        expect($content)->not->toContain('Primeira ação. Segundo café.');
    }

    if ($timestamps) {
        expect($content)->toContain('00:00')->toContain('01:00:03');
    } else {
        expect($content)->not->toContain('00:00')->not->toContain('01:00:03');
    }

    Queue::assertNothingPushed();
    expect($extraction->fresh()->status)->toBe(ExtractionStatus::Ready);
})->with([
    'TXT formatted with timestamps' => ['txt', 'formatted', true],
    'TXT formatted without timestamps' => ['txt', 'formatted', false],
    'TXT segmented with timestamps' => ['txt', 'segmented', true],
    'TXT segmented without timestamps' => ['txt', 'segmented', false],
    'Markdown formatted with timestamps' => ['md', 'formatted', true],
    'Markdown formatted without timestamps' => ['md', 'formatted', false],
    'Markdown segmented with timestamps' => ['md', 'segmented', true],
    'Markdown segmented without timestamps' => ['md', 'segmented', false],
]);

test('download rejects non-ready extraction states and a ready extraction without transcript', function (string $state) {
    $video = Video::factory()->create();
    $extraction = Extraction::query()->create(['video_id' => $video->getKey()]);

    if ($state === 'processing' || $state === 'failed') {
        $extraction->markProcessing();
    }

    if ($state === 'failed') {
        $extraction->markFailed(ExtractionErrorCode::ProviderError, 'internal detail');
    }

    if ($state === 'ready-without-transcript') {
        $extraction->forceFill([
            'status' => ExtractionStatus::Ready,
            'completed_at' => now(),
        ])->save();
    }

    $this->get(route('extractions.download', [
        'extraction' => $extraction,
        'format' => 'txt',
        'mode' => 'formatted',
        'timestamps' => '1',
    ]))->assertConflict();
})->with(['pending', 'processing', 'failed', 'ready-without-transcript']);

test('download route resolves only an existing public ULID', function () {
    $extraction = readyExtractionForDownload();
    $query = '?format=txt&mode=formatted&timestamps=1';

    $this->get("/extractions/{$extraction->public_id}/download{$query}")->assertOk();
    $this->get("/extractions/01K37XW6ZA8XZPB4XWYSP8ZKXY/download{$query}")->assertNotFound();
    $this->get("/extractions/{$extraction->getKey()}/download{$query}")->assertNotFound();
    $this->get("/extractions/not-a-public-id/download{$query}")->assertNotFound();
});

test('download rejects arbitrary options before reaching a renderer', function (array $query, string $invalidField) {
    $extraction = readyExtractionForDownload();

    $this->getJson(route('extractions.download', ['extraction' => $extraction, ...$query]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($invalidField);
})->with([
    'path traversal format' => [['format' => '../../etc/passwd', 'mode' => 'formatted', 'timestamps' => '1'], 'format'],
    'executable format' => [['format' => 'exe', 'mode' => 'formatted', 'timestamps' => '1'], 'format'],
    'arbitrary mode' => [['format' => 'txt', 'mode' => 'shell', 'timestamps' => '1'], 'mode'],
    'arbitrary timestamp' => [['format' => 'txt', 'mode' => 'formatted', 'timestamps' => 'abc'], 'timestamps'],
    'missing options' => [[], 'format'],
]);

test('download filename falls back safely when the title cannot produce a slug', function (?string $title) {
    $extraction = readyExtractionForDownload($title);
    $response = $this->get(route('extractions.download', [
        'extraction' => $extraction,
        'format' => 'txt',
        'mode' => 'formatted',
        'timestamps' => '1',
    ]));

    $response->assertOk();
    expect($response->headers->get('Content-Disposition'))->toContain('youtube-dQw4w9WgXcQ.txt');
})->with(['empty' => '', 'null' => null, 'emoji' => '🎬✨']);
