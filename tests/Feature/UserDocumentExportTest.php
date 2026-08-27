<?php

use App\Actions\EnsureUserTranscript;
use App\Enums\TranscriptSource;
use App\Models\Transcript;
use App\Models\TranscriptSegment;
use App\Models\User;
use App\Models\UserDocument;
use App\Models\UserDocumentRevision;
use App\Models\UserTranscript;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function exportContent(): array
{
    return ['type' => 'doc', 'content' => [
        ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Título *literal*', 'marks' => []]]],
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => '<script>alert("x")</script>', 'marks' => [['type' => 'bold']]], ['type' => 'hardBreak'], ['type' => 'text', 'text' => 'itálico', 'marks' => [['type' => 'italic']]]]],
        ['type' => 'bulletList', 'content' => [['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Item', 'marks' => []]]]]]]],
        ['type' => 'orderedList', 'attrs' => ['start' => 1, 'type' => null], 'content' => [['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Outro', 'marks' => []]]]]]]],
        ['type' => 'blockquote', 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Citação', 'marks' => []]]],
        ]],
    ]];
}

function exportDocument(User $user): UserDocument
{
    $video = Video::factory()->create(['provider_video_id' => 'EXPORT01', 'title' => 'Original']);
    $transcript = Transcript::query()->create(['video_id' => $video->getKey(), 'language_code' => 'pt-BR', 'language_name' => 'Português', 'source' => TranscriptSource::Manual, 'word_count' => 1, 'character_count' => 1, 'extracted_at' => now()]);
    $item = app(EnsureUserTranscript::class)->handle($user->getKey(), $transcript->getKey());
    test()->actingAs($user)->putJson(route('library.document.update', $item), ['title' => 'Meu / documento', 'content' => exportContent(), 'lock_version' => null])->assertCreated();

    return $item->document()->firstOrFail();
}

function exportSeedItem(User $user): UserTranscript
{
    $video = Video::factory()->create(['provider_video_id' => 'EXPORTSEED01', 'title' => 'Seed / documento']);
    $transcript = Transcript::query()->create(['video_id' => $video->getKey(), 'language_code' => 'pt-BR', 'language_name' => 'Português', 'source' => TranscriptSource::Manual, 'word_count' => 4, 'character_count' => 24, 'extracted_at' => now()]);
    TranscriptSegment::query()->create(['transcript_id' => $transcript->getKey(), 'position' => 0, 'start_ms' => 0, 'end_ms' => 1_000, 'text' => 'Conteúdo original do seed.']);

    return app(EnsureUserTranscript::class)->handle($user->getKey(), $transcript->getKey());
}

test('exports the persisted document in txt markdown and safe standalone html', function (string $format, string $contentType) {
    $user = User::factory()->create();
    $document = exportDocument($user);
    $response = $this->actingAs($user)->get(route('library.document.download', [$document->userTranscript->public_id, 'format' => $format]));
    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toBe($contentType.'; charset=UTF-8')
        ->and($response->headers->get('Content-Disposition'))->toContain('meu-documento.'.($format === 'markdown' ? 'md' : $format));
    $body = $response->streamedContent();
    if ($format === 'html') {
        expect($body)->toContain('<!doctype html>')->toContain('&lt;script&gt;')->not->toContain('<script>')->not->toContain('onerror');
    }
    if ($format === 'pdf') {
        expect(substr($body, 0, 4))->toBe('%PDF');
    }
    if ($format === 'docx') {
        expect(substr($body, 0, 2))->toBe('PK');
        $path = tempnam(sys_get_temp_dir(), 'docx-test-');
        file_put_contents($path, $body);
        $zip = new ZipArchive;
        expect($zip->open($path))->toBeTrue();
        expect($zip->getFromName('word/document.xml'))->toContain('script');
        $zip->close();
        unlink($path);
    }
    if ($format === 'markdown') {
        expect($body)->toContain('## Título \\*literal\\*')->toContain('itálico')->toContain('**');
    }
    if ($format === 'txt') {
        expect($body)->toContain('Título *literal*')->toContain('- Item')->toContain('1. Outro')->toContain('> Citação')->toContain('<script>');
    }
})->with([['txt', 'text/plain'], ['markdown', 'text/markdown'], ['html', 'text/html'], ['pdf', 'application/pdf'], ['docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']]);

test('exports the workspace seed in every format without persisting private data', function (string $format, string $contentType) {
    $user = User::factory()->create();
    $item = exportSeedItem($user);

    $response = $this->actingAs($user)->get(route('library.document.download', [$item->public_id, 'format' => $format]));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toBe($contentType.'; charset=UTF-8')
        ->and($response->headers->get('Content-Disposition'))->toContain('seed-documento.'.($format === 'markdown' ? 'md' : $format));
    $body = $response->streamedContent();
    if ($format === 'pdf') {
        expect(substr($body, 0, 4))->toBe('%PDF');
    } elseif ($format === 'docx') {
        expect(substr($body, 0, 2))->toBe('PK');
    } else {
        expect($body)->toContain('Conteúdo original do seed.');
    }
    expect(UserDocument::query()->count())->toBe(0)
        ->and(UserDocumentRevision::query()->count())->toBe(0);
})->with([['txt', 'text/plain'], ['markdown', 'text/markdown'], ['html', 'text/html'], ['pdf', 'application/pdf'], ['docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']]);

test('document export is owner scoped and validates format', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $document = exportDocument($owner);
    $item = $document->userTranscript;
    $this->actingAs($other)->get(route('library.document.download', [$item->public_id, 'format' => 'txt']))->assertNotFound();
    $this->actingAs($owner)->getJson(route('library.document.download', [$item->public_id, 'format' => 'epub']))->assertUnprocessable();
});
