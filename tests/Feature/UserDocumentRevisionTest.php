<?php

use App\Actions\CreateUserDocumentRevision;
use App\Actions\EnsureUserTranscript;
use App\Enums\TranscriptSource;
use App\Enums\UserDocumentRevisionKind;
use App\Models\Transcript;
use App\Models\TranscriptSegment;
use App\Models\User;
use App\Models\UserDocument;
use App\Models\UserDocumentRevision;
use App\Models\UserTranscript;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

function revisionWorkspaceItem(User $user, string $videoId = 'REVISION001'): UserTranscript
{
    $video = Video::factory()->create([
        'provider_video_id' => $videoId,
        'title' => 'Título original',
    ]);
    $transcript = Transcript::query()->create([
        'video_id' => $video->getKey(),
        'language_code' => 'pt-BR',
        'language_name' => 'Português',
        'source' => TranscriptSource::Manual,
        'word_count' => 2,
        'character_count' => 16,
        'extracted_at' => now(),
    ]);
    TranscriptSegment::query()->create([
        'transcript_id' => $transcript->getKey(),
        'position' => 0,
        'start_ms' => 0,
        'end_ms' => 1_000,
        'text' => 'Conteúdo A',
    ]);

    return app(EnsureUserTranscript::class)->handle($user->getKey(), $transcript->getKey());
}

function revisionContent(string $text): array
{
    return [
        'type' => 'doc',
        'content' => [[
            'type' => 'paragraph',
            'content' => [['type' => 'text', 'text' => $text]],
        ]],
    ];
}

function firstRevisionSave(TestCase $test, User $user, UserTranscript $item, string $text = 'Conteúdo B'): UserDocument
{
    $test->actingAs($user)->putJson(route('library.document.update', $item), [
        'title' => 'Documento B',
        'content' => revisionContent($text),
        'lock_version' => null,
    ])->assertCreated();

    return UserDocument::query()->where('user_transcript_id', $item->getKey())->firstOrFail();
}

test('first save atomically creates one original baseline from the seed', function () {
    $user = User::factory()->create();
    $item = revisionWorkspaceItem($user);

    $this->actingAs($user)->get(route('library.workspace', $item))->assertOk();
    expect(UserDocument::query()->count())->toBe(0)
        ->and(UserDocumentRevision::query()->count())->toBe(0);

    $document = firstRevisionSave($this, $user, $item);
    $baseline = UserDocumentRevision::query()->sole();

    expect($document->content)->toBe(revisionContent('Conteúdo B'))
        ->and($baseline->kind)->toBe(UserDocumentRevisionKind::Baseline)
        ->and($baseline->title)->toBe('Título original')
        ->and($baseline->content['content'][0]['content'][0]['text'])->toBe('Conteúdo A')
        ->and($baseline->document_lock_version)->toBe(0)
        ->and($baseline->revision_number)->toBe(1)
        ->and(Str::isUlid($baseline->public_id))->toBeTrue()
        ->and($item->transcript->segments()->sole()->text)->toBe('Conteúdo A');

    $this->putJson(route('library.document.update', $item), [
        'title' => 'Aba atrasada',
        'content' => revisionContent('Outro'),
        'lock_version' => null,
    ])->assertConflict();

    expect(UserDocumentRevision::query()->where('kind', UserDocumentRevisionKind::Baseline->value)->count())->toBe(1);
});

test('automatic checkpoints are spaced and snapshot the persisted state before a real change', function () {
    Carbon::setTestNow('2026-08-26 12:00:00');
    $user = User::factory()->create();
    $item = revisionWorkspaceItem($user);
    firstRevisionSave($this, $user, $item, 'B');

    Carbon::setTestNow('2026-08-26 12:05:00');
    $this->putJson(route('library.document.update', $item), [
        'title' => 'Documento C', 'content' => revisionContent('C'), 'lock_version' => 1,
    ])->assertOk()->assertJsonPath('automaticRevisionCreated', false);

    Carbon::setTestNow('2026-08-26 12:09:00');
    $this->putJson(route('library.document.update', $item), [
        'title' => 'Documento D', 'content' => revisionContent('D'), 'lock_version' => 2,
    ])->assertOk()->assertJsonPath('automaticRevisionCreated', false);

    Carbon::setTestNow('2026-08-26 12:11:00');
    $this->putJson(route('library.document.update', $item), [
        'title' => 'Documento E', 'content' => revisionContent('E'), 'lock_version' => 3,
    ])->assertOk()->assertJsonPath('automaticRevisionCreated', true);

    $automatic = UserDocumentRevision::query()->where('kind', UserDocumentRevisionKind::Automatic->value)->sole();
    expect($automatic->title)->toBe('Documento D')
        ->and($automatic->content)->toBe(revisionContent('D'))
        ->and($automatic->document_lock_version)->toBe(3)
        ->and(UserDocument::query()->sole()->content)->toBe(revisionContent('E'));

    Carbon::setTestNow('2026-08-26 12:30:00');
    $this->putJson(route('library.document.update', $item), [
        'title' => 'Documento E', 'content' => revisionContent('E'), 'lock_version' => 4,
    ])->assertOk()->assertJsonPath('automaticRevisionCreated', false)
        ->assertJsonPath('document.lockVersion', 4);

    expect(UserDocumentRevision::query()->where('kind', UserDocumentRevisionKind::Automatic->value)->count())->toBe(1);
});

test('automatic retention keeps one hundred automatic revisions and never prunes protected kinds', function () {
    $user = User::factory()->create();
    $item = revisionWorkspaceItem($user);
    $document = firstRevisionSave($this, $user, $item);
    $create = app(CreateUserDocumentRevision::class);

    $create->handle($document, UserDocumentRevisionKind::Manual, 'Manual', revisionContent('manual'), 1);
    $create->handle($document, UserDocumentRevisionKind::RestoreBackup, 'Backup', revisionContent('backup'), 1);

    foreach (range(1, 105) as $number) {
        Carbon::setTestNow(now()->addSecond());
        $create->handle(
            $document,
            UserDocumentRevisionKind::Automatic,
            "Automática {$number}",
            revisionContent("automatic-{$number}"),
            1,
        );
    }

    expect($document->revisions()->where('kind', UserDocumentRevisionKind::Automatic->value)->count())->toBe(100)
        ->and($document->revisions()->where('kind', UserDocumentRevisionKind::Baseline->value)->count())->toBe(1)
        ->and($document->revisions()->where('kind', UserDocumentRevisionKind::Manual->value)->count())->toBe(1)
        ->and($document->revisions()->where('kind', UserDocumentRevisionKind::RestoreBackup->value)->count())->toBe(1)
        ->and($document->revisions()->where('title', 'Automática 1')->exists())->toBeFalse()
        ->and($document->revisions()->where('title', 'Automática 105')->exists())->toBeTrue();
});

test('manual revision snapshots server state rejects stale versions and deduplicates current state', function () {
    $user = User::factory()->create();
    $item = revisionWorkspaceItem($user);
    firstRevisionSave($this, $user, $item);
    $url = route('library.document.revisions.store', $item);

    $this->actingAs($user)->postJson($url, ['expected_lock_version' => 1])
        ->assertCreated()
        ->assertJsonPath('created', true)
        ->assertJsonPath('revision.kind', 'manual')
        ->assertJsonPath('revision.documentLockVersion', 1);

    $this->postJson($url, ['expected_lock_version' => 1])
        ->assertOk()
        ->assertJsonPath('created', false)
        ->assertJsonPath('code', 'already_current');

    $this->putJson(route('library.document.update', $item), [
        'title' => 'Documento C', 'content' => revisionContent('C'), 'lock_version' => 1,
    ])->assertOk();
    $this->postJson($url, ['expected_lock_version' => 1])->assertConflict()->assertJsonPath('code', 'document_conflict');

    expect(UserDocumentRevision::query()->where('kind', UserDocumentRevisionKind::Manual->value)->count())->toBe(1)
        ->and(UserDocumentRevision::query()->where('kind', UserDocumentRevisionKind::Manual->value)->sole()->content)
        ->toBe(revisionContent('Conteúdo B'));
});

test('manual and restore requests validate versions and prohibit snapshot data', function () {
    $user = User::factory()->create();
    $item = revisionWorkspaceItem($user);
    firstRevisionSave($this, $user, $item);
    $baseline = UserDocumentRevision::query()->sole();

    $this->actingAs($user)->postJson(route('library.document.revisions.store', $item), [
        'expected_lock_version' => 1,
        'content' => revisionContent('forjado'),
    ])->assertUnprocessable()->assertJsonValidationErrors('content');

    $this->postJson(route('library.document.revisions.restore', [$item, $baseline]), [
        'expected_lock_version' => 0,
        'title' => 'forjado',
    ])->assertUnprocessable()->assertJsonValidationErrors(['expected_lock_version', 'title']);
});

test('restore creates an immutable backup then applies the selected snapshot with optimistic locking', function () {
    $user = User::factory()->create();
    $item = revisionWorkspaceItem($user);
    firstRevisionSave($this, $user, $item, 'B');
    $baseline = UserDocumentRevision::query()->sole();

    $this->putJson(route('library.document.update', $item), [
        'title' => 'Documento C', 'content' => revisionContent('C'), 'lock_version' => 1,
    ])->assertOk();
    $this->postJson(route('library.document.revisions.store', $item), [
        'expected_lock_version' => 2,
    ])->assertCreated();

    $baselineBefore = $baseline->only(['title', 'content', 'document_lock_version']);
    $baselineCreatedAt = $baseline->created_at->toIso8601String();
    $restoreUrl = route('library.document.revisions.restore', [$item, $baseline]);
    $this->postJson($restoreUrl, ['expected_lock_version' => 2])
        ->assertOk()
        ->assertJsonPath('restored', true)
        ->assertJsonPath('backupCreated', true)
        ->assertJsonPath('document.title', 'Título original')
        ->assertJsonPath('document.content.content.0.content.0.text', 'Conteúdo A')
        ->assertJsonPath('document.lockVersion', 3);

    $backup = UserDocumentRevision::query()->where('kind', UserDocumentRevisionKind::RestoreBackup->value)->sole();
    expect($backup->title)->toBe('Documento C')
        ->and($backup->content)->toBe(revisionContent('C'))
        ->and($backup->document_lock_version)->toBe(2)
        ->and($baseline->fresh()->only(['title', 'content', 'document_lock_version']))->toBe($baselineBefore)
        ->and($baseline->fresh()->created_at->toIso8601String())->toBe($baselineCreatedAt)
        ->and($item->transcript->segments()->sole()->text)->toBe('Conteúdo A');

    $this->postJson($restoreUrl, ['expected_lock_version' => 3])
        ->assertOk()
        ->assertJsonPath('restored', false)
        ->assertJsonPath('backupCreated', false)
        ->assertJsonPath('document.lockVersion', 3);

    expect(UserDocumentRevision::query()->where('kind', UserDocumentRevisionKind::RestoreBackup->value)->count())->toBe(1);
});

test('stale restore creates no backup and changes nothing', function () {
    $user = User::factory()->create();
    $item = revisionWorkspaceItem($user);
    $document = firstRevisionSave($this, $user, $item);
    $baseline = UserDocumentRevision::query()->sole();

    $this->actingAs($user)->postJson(route('library.document.revisions.restore', [$item, $baseline]), [
        'expected_lock_version' => 2,
    ])->assertConflict()->assertJsonPath('code', 'document_conflict');

    expect($document->fresh()->content)->toBe(revisionContent('Conteúdo B'))
        ->and($document->fresh()->lock_version)->toBe(1)
        ->and(UserDocumentRevision::query()->count())->toBe(1);
});

test('revision endpoints are owner scoped and never reveal another user data', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $item = revisionWorkspaceItem($owner);
    firstRevisionSave($this, $owner, $item);
    $revision = UserDocumentRevision::query()->sole();

    $this->actingAs($other)->getJson(route('library.document.revisions.index', $item))->assertNotFound();
    $this->getJson(route('library.document.revisions.show', [$item, $revision]))->assertNotFound();
    $this->postJson(route('library.document.revisions.restore', [$item, $revision]), [
        'expected_lock_version' => 1,
    ])->assertNotFound();

    expect(UserDocument::query()->sole()->lock_version)->toBe(1)
        ->and(UserDocumentRevision::query()->count())->toBe(1);
});

test('revision list is paginated newest first and omits content and internal ids', function () {
    $user = User::factory()->create();
    $item = revisionWorkspaceItem($user);
    $document = firstRevisionSave($this, $user, $item);
    $create = app(CreateUserDocumentRevision::class);

    foreach (range(1, 25) as $number) {
        $create->handle($document, UserDocumentRevisionKind::Manual, "Manual {$number}", revisionContent("M{$number}"), 1);
    }

    expect($document->revisions()->orderBy('revision_number')->pluck('revision_number')->all())->toBe(range(1, 26));

    $first = $this->actingAs($user)->getJson(route('library.document.revisions.index', $item));
    $first->assertOk()
        ->assertJsonCount(20, 'data')
        ->assertJsonPath('data.0.revisionNumber', 26)
        ->assertJsonPath('data.0.preview', 'M25')
        ->assertJsonPath('meta.currentPage', 1)
        ->assertJsonPath('meta.lastPage', 2)
        ->assertJsonMissingPath('data.0.content')
        ->assertJsonMissingPath('data.0.id')
        ->assertJsonMissingPath('data.0.userId');

    $this->getJson(route('library.document.revisions.index', [$item, 'page' => 2]))
        ->assertOk()
        ->assertJsonCount(6, 'data')
        ->assertJsonPath('data.5.revisionNumber', 1);

    $latest = UserDocumentRevision::query()->orderByDesc('revision_number')->firstOrFail();
    $this->getJson(route('library.document.revisions.show', [$item, $latest]))
        ->assertOk()
        ->assertJsonPath('revision.content.content.0.content.0.text', 'M25')
        ->assertJsonMissingPath('revision.id');
});

test('deleting a library item cascades document revisions while preserving the original source', function () {
    $user = User::factory()->create();
    $item = revisionWorkspaceItem($user);
    firstRevisionSave($this, $user, $item);
    $transcriptId = $item->transcript_id;
    $videoId = $item->transcript->video_id;

    $this->actingAs($user)->delete(route('library.destroy', $item))->assertRedirect(route('library.index'));

    expect(UserDocument::query()->count())->toBe(0)
        ->and(UserDocumentRevision::query()->count())->toBe(0)
        ->and(Transcript::query()->whereKey($transcriptId)->exists())->toBeTrue()
        ->and(TranscriptSegment::query()->where('transcript_id', $transcriptId)->count())->toBe(1)
        ->and(Video::query()->whereKey($videoId)->exists())->toBeTrue();
});
