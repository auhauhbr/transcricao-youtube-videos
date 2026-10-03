<?php

use App\Actions\EnsureUserTranscript;
use App\Enums\TranscriptSource;
use App\Enums\UserTranscriptAnnotationType;
use App\Models\Transcript;
use App\Models\TranscriptSegment;
use App\Models\User;
use App\Models\UserTranscript;
use App\Models\UserTranscriptAnnotation;
use App\Models\Video;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function annotationItem(User $user, string $videoId = 'ANNOTATE001'): UserTranscript
{
    $video = Video::factory()->create(['provider_video_id' => $videoId, 'duration_seconds' => 90]);
    $transcript = Transcript::query()->create([
        'video_id' => $video->getKey(),
        'language_code' => 'pt-BR',
        'language_name' => 'Português',
        'source' => TranscriptSource::Manual,
        'word_count' => 4,
        'character_count' => 22,
        'extracted_at' => now(),
    ]);

    TranscriptSegment::query()->create(['transcript_id' => $transcript->getKey(), 'position' => 0, 'start_ms' => 0, 'end_ms' => 30_000, 'text' => 'Primeiro bloco original.']);
    TranscriptSegment::query()->create(['transcript_id' => $transcript->getKey(), 'position' => 1, 'start_ms' => 30_000, 'end_ms' => 60_000, 'text' => 'Segundo bloco original.']);

    return app(EnsureUserTranscript::class)->handle($user->getKey(), $transcript->getKey());
}

function annotationStoreUrl(UserTranscript $item): string
{
    return route('library.annotations.store', $item);
}

test('an owner can create and remove a bookmark at a transcript timestamp', function () {
    $user = User::factory()->create();
    $item = annotationItem($user);

    $created = $this->actingAs($user)->postJson(annotationStoreUrl($item), ['start_ms' => 30_000, 'type' => 'bookmark']);

    $created->assertCreated()->assertJsonPath('annotation.startMs', 30_000)->assertJsonPath('annotation.type', 'bookmark');
    $bookmark = UserTranscriptAnnotation::query()->sole();
    expect(Str::isUlid($bookmark->public_id))->toBeTrue()->and($bookmark->text)->toBeNull();

    $this->actingAs($user)->deleteJson(route('library.annotations.destroy', [$item, $bookmark]))->assertNoContent();
    expect(UserTranscriptAnnotation::query()->exists())->toBeFalse();
});

test('an owner can create edit and delete a private note', function () {
    $user = User::factory()->create();
    $item = annotationItem($user);

    $created = $this->actingAs($user)->postJson(annotationStoreUrl($item), ['start_ms' => 0, 'type' => 'note', 'text' => ' Rever este conceito depois. ']);
    $created->assertCreated()->assertJsonPath('annotation.text', 'Rever este conceito depois.');
    $note = UserTranscriptAnnotation::query()->sole();

    $this->actingAs($user)->patchJson(route('library.annotations.update', [$item, $note]), ['text' => 'Nota atualizada.'])
        ->assertOk()->assertJsonPath('annotation.text', 'Nota atualizada.');
    expect($note->refresh()->text)->toBe('Nota atualizada.');

    $this->actingAs($user)->deleteJson(route('library.annotations.destroy', [$item, $note]))->assertNoContent();
    expect(UserTranscriptAnnotation::query()->exists())->toBeFalse();
});

test('annotation requests validate timestamps note text and duplicates', function () {
    $user = User::factory()->create();
    $item = annotationItem($user);

    $this->actingAs($user)->postJson(annotationStoreUrl($item), ['start_ms' => -1, 'type' => 'bookmark'])->assertUnprocessable()->assertJsonValidationErrors('start_ms');
    $this->actingAs($user)->postJson(annotationStoreUrl($item), ['start_ms' => 12_345, 'type' => 'bookmark'])->assertUnprocessable()->assertJsonValidationErrors('start_ms');
    $this->actingAs($user)->postJson(annotationStoreUrl($item), ['start_ms' => 0, 'type' => 'note', 'text' => '   '])->assertUnprocessable()->assertJsonValidationErrors('text');
    $this->actingAs($user)->postJson(annotationStoreUrl($item), ['start_ms' => 0, 'type' => 'note', 'text' => str_repeat('a', 4_001)])->assertUnprocessable()->assertJsonValidationErrors('text');

    $this->actingAs($user)->postJson(annotationStoreUrl($item), ['start_ms' => 0, 'type' => 'bookmark'])->assertCreated();
    $this->actingAs($user)->postJson(annotationStoreUrl($item), ['start_ms' => 0, 'type' => 'bookmark'])->assertUnprocessable()->assertJsonValidationErrors('type');
    $this->actingAs($user)->postJson(annotationStoreUrl($item), ['start_ms' => 0, 'type' => 'note', 'text' => 'Única nota.'])->assertCreated();
    $this->actingAs($user)->postJson(annotationStoreUrl($item), ['start_ms' => 0, 'type' => 'note', 'text' => 'Outra nota.'])->assertUnprocessable()->assertJsonValidationErrors('type');
});

test('annotations are private and all cross user mutation attempts return not found', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $item = annotationItem($owner);
    $note = UserTranscriptAnnotation::factory()->for($item, 'userTranscript')->create(['start_ms' => 0, 'type' => UserTranscriptAnnotationType::Note, 'text' => 'Privada']);

    $this->actingAs($other)->postJson(annotationStoreUrl($item), ['start_ms' => 0, 'type' => 'bookmark'])->assertNotFound();
    $this->actingAs($other)->patchJson(route('library.annotations.update', [$item, $note]), ['text' => 'Invasão'])->assertNotFound();
    $this->actingAs($other)->deleteJson(route('library.annotations.destroy', [$item, $note]))->assertNotFound();

    $otherItem = annotationItem($other, 'ANNOTATE002');
    $this->actingAs($other)->patchJson(route('library.annotations.update', [$otherItem, $note]), ['text' => 'Invasão'])->assertNotFound();
    $this->actingAs($other)->deleteJson(route('library.annotations.destroy', [$otherItem, $note]))->assertNotFound();
    expect($note->refresh()->text)->toBe('Privada');
});

test('annotation props are only exposed to the owner in transcript and workspace views', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $item = annotationItem($owner);
    UserTranscriptAnnotation::factory()->for($item, 'userTranscript')->create(['start_ms' => 30_000, 'type' => UserTranscriptAnnotationType::Note, 'text' => 'Ver no painel']);

    $this->actingAs($owner)->get(route('library.show', $item))->assertInertia(fn (Assert $page) => $page
        ->where('annotations.items.0.startMs', 30_000)
        ->where('annotations.items.0.text', 'Ver no painel')
        ->where('annotations.urls.store', "/library/{$item->public_id}/annotations")
        ->missing('annotations.items.0.id')
        ->missing('annotations.items.0.userTranscriptId')
    );
    $this->actingAs($owner)->get(route('library.workspace', $item))->assertInertia(fn (Assert $page) => $page
        ->where('workspace.annotations.items.0.startMs', 30_000)
        ->where('workspace.annotations.items.0.text', 'Ver no painel')
    );
    $this->actingAs($other)->get(route('library.show', $item))->assertNotFound();
    $this->actingAs($other)->get(route('library.workspace', $item))->assertNotFound();
});

test('deleting a library item cascades annotations and never changes the original transcript', function () {
    $user = User::factory()->create();
    $item = annotationItem($user);
    $transcriptId = $item->transcript_id;
    UserTranscriptAnnotation::factory()->for($item, 'userTranscript')->bookmark()->create(['start_ms' => 0]);

    $this->actingAs($user)->delete(route('library.destroy', $item))->assertRedirect(route('library.index'));

    expect(UserTranscriptAnnotation::query()->count())->toBe(0)
        ->and(Transcript::query()->whereKey($transcriptId)->exists())->toBeTrue()
        ->and(TranscriptSegment::query()->where('transcript_id', $transcriptId)->count())->toBe(2);
});

test('database uniqueness prevents duplicate annotation types at the same timestamp', function () {
    $item = annotationItem(User::factory()->create());
    UserTranscriptAnnotation::factory()->for($item, 'userTranscript')->bookmark()->create(['start_ms' => 0]);

    expect(fn () => UserTranscriptAnnotation::factory()->for($item, 'userTranscript')->bookmark()->create(['start_ms' => 0]))
        ->toThrow(UniqueConstraintViolationException::class);
});

test('annotations are excluded from original and document exports and guests cannot access their endpoints', function () {
    $owner = User::factory()->create();
    $item = annotationItem($owner);
    UserTranscriptAnnotation::factory()->for($item, 'userTranscript')->create(['start_ms' => 0, 'text' => 'Não exportar esta nota.']);

    $this->actingAs($owner)->get(route('library.download', [$item, 'format' => 'txt', 'mode' => 'formatted', 'timestamps' => '1']))
        ->assertOk()
        ->assertDontSee('Não exportar esta nota.');
    auth()->logout();
    $this->postJson(annotationStoreUrl($item), ['start_ms' => 0, 'type' => 'bookmark'])->assertUnauthorized();
    $this->get(route('library.show', $item))->assertRedirect(route('login'));
});
