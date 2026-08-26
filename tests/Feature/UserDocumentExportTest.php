<?php

use App\Actions\EnsureUserTranscript;
use App\Enums\TranscriptSource;
use App\Models\Transcript;
use App\Models\User;
use App\Models\UserDocument;
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
    if ($format === 'markdown') {
        expect($body)->toContain('## Título \\*literal\\*')->toContain('itálico')->toContain('**');
    }
    if ($format === 'txt') {
        expect($body)->toContain('Título *literal*')->toContain('- Item')->toContain('1. Outro')->toContain('> Citação')->toContain('<script>');
    }
})->with([['txt', 'text/plain'], ['markdown', 'text/markdown'], ['html', 'text/html']]);

test('document export is owner scoped, lazy and validates format', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $document = exportDocument($owner);
    $item = $document->userTranscript;
    $missing = app(EnsureUserTranscript::class)->handle($owner->getKey(), Transcript::query()->create(['video_id' => Video::factory()->create()->getKey(), 'language_code' => 'pt-BR', 'source' => TranscriptSource::Manual, 'word_count' => 0, 'character_count' => 0, 'extracted_at' => now()])->getKey());
    $this->actingAs($owner)->get(route('library.document.download', [$missing->public_id, 'format' => 'txt']))->assertNotFound();
    $this->actingAs($other)->get(route('library.document.download', [$item->public_id, 'format' => 'txt']))->assertNotFound();
    $this->actingAs($owner)->getJson(route('library.document.download', [$item->public_id, 'format' => 'pdf']))->assertUnprocessable();
});
