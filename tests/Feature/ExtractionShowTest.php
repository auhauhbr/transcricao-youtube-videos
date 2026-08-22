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
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function extractionForShow(ExtractionStatus $status = ExtractionStatus::Pending): Extraction
{
    $video = Video::factory()->create([
        'provider_video_id' => 'dQw4w9WgXcQ',
    ]);
    $extraction = Extraction::query()->create(['video_id' => $video->getKey()]);

    if ($status === ExtractionStatus::Processing) {
        $extraction->markProcessing();
    }

    return $extraction;
}

function readyExtractionForShow(): Extraction
{
    $video = Video::factory()->create([
        'provider_video_id' => 'dQw4w9WgXcQ',
        'title' => 'Vídeo persistido',
        'channel_name' => 'Canal seguro',
        'channel_id' => 'UC-safe-channel-id',
        'duration_seconds' => 125,
        'thumbnail_url' => 'https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg',
        'metadata' => ['private' => 'must not leak'],
    ]);
    $transcript = Transcript::query()->create([
        'video_id' => $video->getKey(),
        'language_code' => 'pt-BR',
        'language_name' => 'Português',
        'source' => TranscriptSource::Manual,
        'word_count' => 3,
        'character_count' => 20,
        'extracted_at' => now(),
    ]);

    foreach ([
        ['position' => 2, 'start_ms' => 2000, 'end_ms' => 3000, 'text' => 'Terceiro'],
        ['position' => 0, 'start_ms' => 0, 'end_ms' => 1000, 'text' => 'Primeiro'],
        ['position' => 1, 'start_ms' => 1000, 'end_ms' => 2000, 'text' => 'Segundo'],
    ] as $segment) {
        TranscriptSegment::query()->create(['transcript_id' => $transcript->getKey(), ...$segment]);
    }

    foreach ([
        ['position' => 1, 'title' => 'Fim', 'start_ms' => 2000, 'end_ms' => 3000],
        ['position' => 0, 'title' => 'Início', 'start_ms' => 0, 'end_ms' => 2000],
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

test('a pending extraction has a small polling payload', function () {
    $extraction = extractionForShow();

    $this->get(route('extractions.show', $extraction))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Extractions/Show')
            ->where('extraction.publicId', $extraction->public_id)
            ->where('extraction.status', 'pending')
            ->where('extraction.startedAt', null)
            ->where('extraction.completedAt', null)
            ->where('video.providerVideoId', 'dQw4w9WgXcQ')
            ->where('video.title', null)
            ->missing('transcript')
            ->missing('failureMessage')
        );
});

test('a processing extraction has timestamps but no transcript payload', function () {
    $extraction = extractionForShow(ExtractionStatus::Processing);

    $this->get(route('extractions.show', $extraction))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Extractions/Show')
            ->where('extraction.status', 'processing')
            ->where('extraction.startedAt', fn ($value): bool => is_string($value))
            ->where('extraction.completedAt', null)
            ->missing('transcript')
            ->missing('failureMessage')
        );
});

test('a ready extraction exposes only ordered public transcript data', function () {
    $extraction = readyExtractionForShow();

    $this->get(route('extractions.show', $extraction))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Extractions/Show')
            ->where('extraction.status', 'ready')
            ->where('video.providerVideoId', 'dQw4w9WgXcQ')
            ->where('video.title', 'Vídeo persistido')
            ->where('video.channelName', 'Canal seguro')
            ->where('video.channelId', 'UC-safe-channel-id')
            ->where('video.thumbnailUrl', 'https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg')
            ->where('video.durationSeconds', 125)
            ->where('video.youtubeUrl', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ')
            ->where('transcript.languageCode', 'pt-BR')
            ->where('transcript.languageName', 'Português')
            ->where('transcript.source', 'manual')
            ->where('transcript.wordCount', 3)
            ->where('transcript.characterCount', 20)
            ->where('transcript.blocks.0.position', 0)
            ->where('transcript.blocks.0.startMs', 0)
            ->where('transcript.blocks.0.endMs', 2000)
            ->where('transcript.blocks.0.text', 'Primeiro Segundo')
            ->where('transcript.blocks.0.chapterPosition', 0)
            ->where('transcript.blocks.1.position', 1)
            ->where('transcript.blocks.1.text', 'Terceiro')
            ->where('transcript.blocks.1.chapterPosition', 1)
            ->missing('transcript.segments')
            ->where('transcript.chapters.0.position', 0)
            ->where('transcript.chapters.1.position', 1)
            ->missing('failureMessage')
            ->missing('extraction.id')
            ->missing('extraction.user_id')
            ->missing('video.id')
            ->missing('video.metadata')
            ->missing('video.channel_id')
            ->missing('video.thumbnail_url')
            ->missing('transcript.id')
            ->missing('transcript.extracted_at')
        );
});

test('failed extractions expose mapped public messages without technical details', function (ExtractionErrorCode $code, string $message) {
    $extraction = extractionForShow();
    $extraction->markProcessing();
    $extraction->markFailed($code, 'stderr: command failed with secret internal detail');

    $this->get(route('extractions.show', $extraction))
        ->assertOk()
        ->assertDontSee('secret internal detail')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Extractions/Show')
            ->where('extraction.status', 'failed')
            ->where('failureMessage', $message)
            ->missing('extraction.error_code')
            ->missing('extraction.error_message')
            ->missing('transcript')
        );
})->with([
    'transcript unavailable' => [ExtractionErrorCode::TranscriptNotAvailable, 'Este vídeo não possui uma transcrição disponível.'],
    'video unavailable' => [ExtractionErrorCode::VideoUnavailable, 'Este vídeo está indisponível.'],
    'provider blocked' => [ExtractionErrorCode::ProviderBlocked, 'Não foi possível acessar o YouTube neste momento. Tente novamente mais tarde.'],
    'provider timeout' => [ExtractionErrorCode::ProviderTimeout, 'A extração demorou mais que o esperado. Tente novamente.'],
    'output limit' => [ExtractionErrorCode::OutputLimit, 'A transcrição é grande demais para ser processada.'],
    'provider error' => [ExtractionErrorCode::ProviderError, 'Não foi possível obter a transcrição. Tente novamente mais tarde.'],
]);

test('only an existing ULID public id resolves an extraction', function () {
    $extraction = extractionForShow();

    $this->get('/extractions/'.$extraction->public_id)->assertOk();
    $this->get('/extractions/01K37XW6ZA8XZPB4XWYSP8ZKXY')->assertNotFound();
    $this->get('/extractions/'.$extraction->getKey())->assertNotFound();
    $this->get('/extractions/not-a-public-id')->assertNotFound();
});

test('a ready extraction without a transcript renders a safe public failure', function () {
    $extraction = extractionForShow();
    $extraction->forceFill([
        'status' => ExtractionStatus::Ready,
        'completed_at' => now(),
    ])->save();

    $this->get(route('extractions.show', $extraction))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Extractions/Show')
            ->where('extraction.status', 'failed')
            ->where('failureMessage', 'Não foi possível exibir esta transcrição. Tente novamente mais tarde.')
            ->missing('transcript')
        );
});
