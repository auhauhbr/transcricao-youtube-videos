<?php

use App\Actions\EnsureUserTranscript;
use App\Enums\TranscriptSource;
use App\Enums\UserDocumentRevisionKind;
use App\Models\Folder;
use App\Models\SocialAccount;
use App\Models\Tag;
use App\Models\Transcript;
use App\Models\TranscriptSegment;
use App\Models\User;
use App\Models\UserDocument;
use App\Models\UserDocumentRevision;
use App\Models\UserTranscript;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function privacyDocumentContent(): array
{
    return ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Conteúdo privado']]]]];
}

function privacyItem(User $user): UserTranscript
{
    $video = Video::factory()->create(['provider_video_id' => 'PRIVACY0001', 'title' => 'Vídeo privado']);
    $transcript = Transcript::query()->create([
        'video_id' => $video->getKey(),
        'language_code' => 'pt-BR',
        'language_name' => 'Português',
        'source' => TranscriptSource::Manual,
        'word_count' => 2,
        'character_count' => 18,
        'extracted_at' => now(),
    ]);
    TranscriptSegment::query()->create([
        'transcript_id' => $transcript->getKey(),
        'position' => 0,
        'start_ms' => 0,
        'end_ms' => 1_000,
        'text' => 'Conteúdo privado',
    ]);

    return app(EnsureUserTranscript::class)->handle($user->getKey(), $transcript->getKey());
}

test('private resources are owner scoped and 404 without leaking another user identity', function () {
    $userA = User::factory()->create(['name' => 'Usuário A', 'email' => 'a@example.com']);
    $userB = User::factory()->create(['name' => 'Usuário B Privado', 'email' => 'b@example.com']);
    $item = privacyItem($userB);
    $document = UserDocument::query()->create([
        'user_transcript_id' => $item->getKey(),
        'title' => 'Documento privado',
        'content' => privacyDocumentContent(),
        'lock_version' => 1,
    ]);
    $revision = UserDocumentRevision::query()->create([
        'user_document_id' => $document->getKey(),
        'revision_number' => 1,
        'kind' => UserDocumentRevisionKind::Baseline,
        'title' => $document->title,
        'content' => $document->content,
        'document_lock_version' => 0,
    ]);
    $folder = Folder::query()->create(['user_id' => $userB->getKey(), 'name' => 'Pasta privada']);
    $tag = Tag::query()->create(['user_id' => $userB->getKey(), 'name' => 'Tag privada']);
    SocialAccount::query()->create(['user_id' => $userB->getKey(), 'provider' => 'google', 'provider_user_id' => 'google-private-id']);

    $responses = [
        $this->actingAs($userA)->get(route('library.show', $item)),
        $this->actingAs($userA)->get(route('library.workspace', $item)),
        $this->actingAs($userA)->get(route('library.download', [$item, 'format' => 'txt', 'mode' => 'formatted', 'timestamps' => '1'])),
        $this->actingAs($userA)->get(route('library.document.download', [$item, 'format' => 'txt'])),
        $this->actingAs($userA)->get(route('library.document.revisions.index', $item)),
        $this->actingAs($userA)->get(route('library.document.revisions.show', [$item, $revision])),
        $this->actingAs($userA)->putJson(route('library.document.update', $item), ['title' => 'Tentativa', 'content' => privacyDocumentContent(), 'lock_version' => 1]),
        $this->actingAs($userA)->delete(route('library.destroy', $item)),
        $this->actingAs($userA)->patch(route('library.folders.update', $folder), ['name' => 'Tentativa']),
        $this->actingAs($userA)->patch(route('library.tags.update', $tag), ['name' => 'Tentativa']),
        $this->actingAs($userA)->patch(route('library.items.move'), ['item_public_ids' => [$item->public_id], 'folder_public_id' => $folder->public_id]),
        $this->actingAs($userA)->post(route('library.items.tags.add'), ['item_public_ids' => [$item->public_id], 'tag_public_ids' => [$tag->public_id]]),
        $this->actingAs($userA)->delete(route('library.items.destroy'), ['item_public_ids' => [$item->public_id]]),
    ];

    foreach ($responses as $response) {
        $response->assertNotFound()
            ->assertDontSee('Usuário B Privado')
            ->assertDontSee('b@example.com')
            ->assertDontSee('google-private-id');
    }

    expect($document->refresh()->title)->toBe('Documento privado')
        ->and($item->refresh()->exists)->toBeTrue()
        ->and($folder->refresh()->name)->toBe('Pasta privada')
        ->and($tag->refresh()->name)->toBe('Tag privada');
});

test('social account identifiers are hidden from serialization', function () {
    $account = SocialAccount::query()->create([
        'user_id' => User::factory()->create()->getKey(),
        'provider' => 'microsoft',
        'provider_user_id' => 'private-provider-id',
    ]);

    expect($account->toArray())
        ->not->toHaveKey('user_id')
        ->not->toHaveKey('provider')
        ->not->toHaveKey('provider_user_id');
});

test('default web responses include the baseline security headers', function () {
    $response = $this->get(route('home'));

    $response->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
});
