<?php

use App\Actions\EnsureUserTranscript;
use App\Enums\ChapterSource;
use App\Enums\TranscriptSource;
use App\Models\Chapter;
use App\Models\Transcript;
use App\Models\TranscriptSegment;
use App\Models\User;
use App\Models\UserDocument;
use App\Models\UserTranscript;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function workspaceItem(User $user, string $videoId = 'WORKSPACE01', bool $withChapters = true): UserTranscript
{
    $video = Video::factory()->create([
        'provider_video_id' => $videoId,
        'title' => 'Título original',
        'channel_name' => 'Canal original',
        'duration_seconds' => 120,
    ]);
    $transcript = Transcript::query()->create([
        'video_id' => $video->getKey(),
        'language_code' => 'pt-BR',
        'language_name' => 'Português',
        'source' => TranscriptSource::Manual,
        'word_count' => 6,
        'character_count' => 42,
        'extracted_at' => now(),
    ]);

    foreach ([
        ['position' => 2, 'start_ms' => 60_000, 'end_ms' => 90_000, 'text' => 'Terceiro bloco original.'],
        ['position' => 0, 'start_ms' => 0, 'end_ms' => 30_000, 'text' => 'Primeiro bloco original.'],
        ['position' => 1, 'start_ms' => 30_000, 'end_ms' => 60_000, 'text' => 'Segundo bloco original.'],
    ] as $segment) {
        TranscriptSegment::query()->create(['transcript_id' => $transcript->getKey(), ...$segment]);
    }

    if ($withChapters) {
        foreach ([
            ['position' => 0, 'title' => 'Introdução', 'start_ms' => 0, 'end_ms' => 60_000],
            ['position' => 1, 'title' => 'Conclusão', 'start_ms' => 60_000, 'end_ms' => 120_000],
        ] as $chapter) {
            Chapter::query()->create([
                'transcript_id' => $transcript->getKey(),
                'source' => ChapterSource::Provider,
                ...$chapter,
            ]);
        }
    }

    return app(EnsureUserTranscript::class)->handle($user->getKey(), $transcript->getKey());
}

function editableDocument(string $text = 'Conteúdo editável'): array
{
    return [
        'type' => 'doc',
        'content' => [[
            'type' => 'paragraph',
            'content' => [[
                'type' => 'text',
                'text' => $text,
                'marks' => [['type' => 'bold'], ['type' => 'italic']],
            ]],
        ]],
    ];
}

test('workspace endpoints require authentication', function () {
    $item = workspaceItem(User::factory()->create());

    $this->get(route('library.workspace', $item))->assertRedirect(route('login'));
    $this->putJson(route('library.document.update', $item), [
        'title' => 'Documento',
        'content' => editableDocument(),
        'lock_version' => null,
    ])->assertUnauthorized();
});

test('workspace get is owner scoped lazy and exposes a readable seed without internal ids', function () {
    $owner = User::factory()->create();
    $item = workspaceItem($owner);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $response = $this->actingAs($owner)->get(route('library.workspace', $item));
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Workspace/Show')
            ->where('workspace.userTranscriptPublicId', $item->public_id)
            ->where('workspace.document', null)
            ->where('workspace.seed.title', 'Título original')
            ->where('workspace.seed.content.type', 'doc')
            ->where('workspace.seed.content.content.0.type', 'heading')
            ->where('workspace.seed.content.content.0.attrs.level', 2)
            ->where('workspace.seed.content.content.0.content.0.text', 'Introdução')
            ->where('workspace.seed.content.content.1.content.0.text', 'Primeiro bloco original.')
            ->where('workspace.seed.content.content.2.content.0.text', 'Segundo bloco original.')
            ->where('workspace.seed.content.content.3.content.0.text', 'Conclusão')
            ->where('workspace.seed.content.content.4.content.0.text', 'Terceiro bloco original.')
            ->where('workspace.source.transcript.blocks.0.startMs', 0)
            ->where('workspace.urls.save', "/library/{$item->public_id}/document")
            ->missing('workspace.source.transcript.segments')
            ->missing('workspace.source.video.id')
            ->missing('workspace.transcriptId')
        );

    expect(UserDocument::query()->count())->toBe(0)
        ->and($queryCount)->toBeLessThan(10);
});

test('first save creates one private document and never mutates transcript source data', function () {
    $user = User::factory()->create();
    $item = workspaceItem($user);
    $transcript = $item->transcript;
    $originalSegments = $transcript->segments()->pluck('text')->all();

    $response = $this->actingAs($user)->putJson(route('library.document.update', $item), [
        'title' => 'Meu documento',
        'content' => editableDocument('Texto B'),
        'lock_version' => null,
    ]);

    $response->assertCreated()
        ->assertJsonPath('document.title', 'Meu documento')
        ->assertJsonPath('document.content.content.0.content.0.text', 'Texto B')
        ->assertJsonPath('document.lockVersion', 1);
    $document = UserDocument::query()->sole();
    expect(Str::isUlid($document->public_id))->toBeTrue()
        ->and($document->userTranscript->is($item))->toBeTrue()
        ->and(UserDocument::query()->count())->toBe(1)
        ->and($transcript->fresh()->video->title)->toBe('Título original')
        ->and($transcript->segments()->pluck('text')->all())->toBe($originalSegments);
});

test('repeated saves update the unique document with optimistic locking', function () {
    $user = User::factory()->create();
    $item = workspaceItem($user);
    $url = route('library.document.update', $item);

    $this->actingAs($user)->putJson($url, ['title' => 'V1', 'content' => editableDocument('V1'), 'lock_version' => null])
        ->assertCreated()->assertJsonPath('document.lockVersion', 1);
    $this->putJson($url, ['title' => 'V2', 'content' => editableDocument('V2'), 'lock_version' => 1])
        ->assertOk()->assertJsonPath('document.lockVersion', 2);
    $this->putJson($url, ['title' => 'Stale', 'content' => editableDocument('Stale'), 'lock_version' => 1])
        ->assertConflict()->assertJsonPath('code', 'document_conflict');

    expect(UserDocument::query()->count())->toBe(1)
        ->and(UserDocument::query()->sole()->title)->toBe('V2')
        ->and(UserDocument::query()->sole()->lock_version)->toBe(2);
});

test('a first save loses safely when another tab already created the document', function () {
    $user = User::factory()->create();
    $item = workspaceItem($user);
    $url = route('library.document.update', $item);

    $this->actingAs($user)->putJson($url, ['title' => 'Aba vencedora', 'content' => editableDocument('A'), 'lock_version' => null])->assertCreated();
    $this->putJson($url, ['title' => 'Aba antiga', 'content' => editableDocument('B'), 'lock_version' => null])
        ->assertConflict()->assertJsonPath('code', 'document_conflict');

    expect(UserDocument::query()->sole()->title)->toBe('Aba vencedora');
});

test('another user receives 404 for workspace and save without creating a document', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $item = workspaceItem($owner);

    $this->actingAs($other)->get(route('library.workspace', $item))->assertNotFound();
    $this->putJson(route('library.document.update', $item), [
        'title' => 'Invasão',
        'content' => editableDocument(),
        'lock_version' => null,
    ])->assertNotFound();

    expect(UserDocument::query()->count())->toBe(0);
});

test('save validates title document schema marks headings attributes and payload size', function (array $changes, string $errorKey) {
    $user = User::factory()->create();
    $item = workspaceItem($user);
    $payload = ['title' => 'Documento', 'content' => editableDocument(), 'lock_version' => null];

    $this->actingAs($user)->putJson(route('library.document.update', $item), array_replace($payload, $changes))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($errorKey);

    expect(UserDocument::query()->count())->toBe(0);
})->with([
    'empty title' => [['title' => ''], 'title'],
    'long title' => [['title' => str_repeat('a', 256)], 'title'],
    'malformed content' => [['content' => 'not-json-document'], 'content'],
    'unsupported node' => [['content' => ['type' => 'doc', 'content' => [['type' => 'image', 'attrs' => ['src' => 'x']]]]], 'content'],
    'unsupported mark' => [['content' => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'X', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'javascript:alert(1)']]]]]]]]], 'content'],
    'invalid heading level' => [['content' => ['type' => 'doc', 'content' => [['type' => 'heading', 'attrs' => ['level' => 1], 'content' => [['type' => 'text', 'text' => 'X']]]]]], 'content'],
    'unexpected attributes' => [['content' => ['type' => 'doc', 'attrs' => ['onclick' => 'alert(1)'], 'content' => []]], 'content'],
    'oversized payload' => [['content' => editableDocument(str_repeat('x', 5 * 1024 * 1024))], 'content'],
]);

test('empty document is valid', function () {
    $user = User::factory()->create();
    $item = workspaceItem($user);

    $this->actingAs($user)->putJson(route('library.document.update', $item), [
        'title' => 'Documento vazio',
        'content' => ['type' => 'doc', 'content' => [['type' => 'paragraph']]],
        'lock_version' => null,
    ])->assertCreated();
});

test('the complete editor subset is accepted with only safe structured attributes', function () {
    $user = User::factory()->create();
    $item = workspaceItem($user);
    $paragraph = fn (string $text): array => ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]];
    $listItem = fn (string $text): array => ['type' => 'listItem', 'content' => [$paragraph($text)]];
    $content = [
        'type' => 'doc',
        'content' => [
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Título', 'marks' => [['type' => 'bold']]]]],
            ['type' => 'heading', 'attrs' => ['level' => 3], 'content' => [['type' => 'text', 'text' => 'Subtítulo', 'marks' => [['type' => 'italic']]]]],
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Linha um'], ['type' => 'hardBreak'], ['type' => 'text', 'text' => 'Linha dois']]],
            ['type' => 'bulletList', 'content' => [$listItem('Marcador')]],
            ['type' => 'orderedList', 'attrs' => ['start' => 1, 'type' => null], 'content' => [$listItem('Numerada')]],
            ['type' => 'blockquote', 'content' => [$paragraph('Citação')]],
        ],
    ];

    $this->actingAs($user)->putJson(route('library.document.update', $item), [
        'title' => 'Schema seguro',
        'content' => $content,
        'lock_version' => null,
    ])->assertCreated()->assertJsonPath('document.content', $content);
});

test('save preserves whitespace text nodes emitted between formatted ranges', function () {
    $user = User::factory()->create();
    $item = workspaceItem($user);
    $content = [
        'type' => 'doc',
        'content' => [[
            'type' => 'paragraph',
            'content' => [
                ['type' => 'text', 'text' => 'Negrito', 'marks' => [['type' => 'bold']]],
                ['type' => 'text', 'text' => ' '],
                ['type' => 'text', 'text' => 'itálico', 'marks' => [['type' => 'italic']]],
            ],
        ]],
    ];

    $this->actingAs($user)->putJson(route('library.document.update', $item), [
        'title' => '  Título normalizado  ',
        'content' => $content,
        'lock_version' => null,
    ])->assertCreated()
        ->assertJsonPath('document.title', 'Título normalizado')
        ->assertJsonPath('document.content', $content);
});

test('removing a library item cascades its document but preserves global source records', function () {
    $user = User::factory()->create();
    $item = workspaceItem($user);
    $transcriptId = $item->transcript_id;
    $videoId = $item->transcript->video_id;
    $this->actingAs($user)->putJson(route('library.document.update', $item), [
        'title' => 'Privado',
        'content' => editableDocument(),
        'lock_version' => null,
    ])->assertCreated();

    $this->delete(route('library.destroy', $item))->assertRedirect(route('library.index'));

    expect(UserTranscript::query()->whereKey($item->getKey())->exists())->toBeFalse()
        ->and(UserDocument::query()->count())->toBe(0)
        ->and(Transcript::query()->whereKey($transcriptId)->exists())->toBeTrue()
        ->and(TranscriptSegment::query()->where('transcript_id', $transcriptId)->count())->toBe(3)
        ->and(Chapter::query()->where('transcript_id', $transcriptId)->count())->toBe(2)
        ->and(Video::query()->whereKey($videoId)->exists())->toBeTrue();
});
