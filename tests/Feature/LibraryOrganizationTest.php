<?php

use App\Actions\EnsureUserTranscript;
use App\Enums\TranscriptSource;
use App\Models\Folder;
use App\Models\Tag;
use App\Models\Transcript;
use App\Models\TranscriptSegment;
use App\Models\User;
use App\Models\UserTranscript;
use App\Models\Video;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function organizedTranscript(User $user, string $videoId, string $title, array $attributes = []): UserTranscript
{
    $video = Video::factory()->create([
        'provider_video_id' => $videoId,
        'title' => $title,
        'channel_name' => $attributes['channel'] ?? 'Canal padrão',
        'duration_seconds' => 120,
    ]);
    $transcript = Transcript::query()->create([
        'video_id' => $video->getKey(),
        'language_code' => $attributes['language'] ?? 'pt-BR',
        'language_name' => $attributes['language_name'] ?? 'Português',
        'source' => $attributes['source'] ?? TranscriptSource::Manual,
        'word_count' => 2,
        'character_count' => 10,
        'extracted_at' => now(),
    ]);

    return app(EnsureUserTranscript::class)->handle($user->getKey(), $transcript->getKey());
}

test('folders use opaque ULIDs belong to one user and validate trimmed unique names', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('library.folders.store'), ['name' => '  Faculdade  '])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', 'folder-created');

    $folder = Folder::query()->sole();
    expect(Str::isUlid($folder->public_id))->toBeTrue()
        ->and($folder->name)->toBe('Faculdade')
        ->and($folder->user->is($user))->toBeTrue();

    $this->actingAs($user)->post(route('library.folders.store'), ['name' => '   '])->assertSessionHasErrors('name');
    $this->actingAs($user)->post(route('library.folders.store'), ['name' => 'Faculdade'])->assertSessionHasErrors('name');
});

test('renaming and deleting a folder are owner scoped and deletion only unfiles items', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $folder = $owner->folders()->create(['name' => 'Estudos']);
    $first = organizedTranscript($owner, 'ORGFOLDER01', 'Primeiro')->update(['folder_id' => $folder->getKey()]);
    $secondItem = organizedTranscript($owner, 'ORGFOLDER02', 'Segundo');
    $secondItem->update(['folder_id' => $folder->getKey()]);
    $transcriptId = $secondItem->transcript_id;

    $this->actingAs($other)->patch(route('library.folders.update', $folder), ['name' => 'Invasão'])->assertNotFound();
    $this->actingAs($other)->delete(route('library.folders.destroy', $folder))->assertNotFound();
    $this->actingAs($owner)->patch(route('library.folders.update', $folder), ['name' => 'Faculdade'])->assertSessionHasNoErrors();
    $this->actingAs($owner)->delete(route('library.folders.destroy', $folder))->assertSessionHas('status', 'folder-deleted');

    expect(Folder::query()->whereKey($folder->getKey())->exists())->toBeFalse()
        ->and(UserTranscript::query()->where('folder_id', $folder->getKey())->count())->toBe(0)
        ->and(UserTranscript::query()->where('user_id', $owner->getKey())->whereNull('folder_id')->count())->toBe(2)
        ->and(Transcript::query()->whereKey($transcriptId)->exists())->toBeTrue();
});

test('tags are private ULID resources and database uniqueness prevents duplicate pivots', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $item = organizedTranscript($owner, 'ORGTAGS0001', 'Tags privadas');

    $this->actingAs($owner)->post(route('library.tags.store'), ['name' => '  PHP '])->assertSessionHasNoErrors();
    $tag = Tag::query()->sole();
    expect(Str::isUlid($tag->public_id))->toBeTrue()->and($tag->name)->toBe('PHP');

    $payload = ['item_public_ids' => [$item->public_id], 'tag_public_ids' => [$tag->public_id]];
    $this->actingAs($owner)->post(route('library.items.tags.add'), $payload)->assertSessionHasNoErrors();
    $this->actingAs($owner)->post(route('library.items.tags.add'), $payload)->assertSessionHasNoErrors();
    expect(DB::table('tag_user_transcript')->count())->toBe(1);
    expect(fn () => DB::table('tag_user_transcript')->insert(['tag_id' => $tag->getKey(), 'user_transcript_id' => $item->getKey()]))->toThrow(QueryException::class);

    $this->actingAs($other)->patch(route('library.tags.update', $tag), ['name' => 'Invadida'])->assertNotFound();
    $this->actingAs($other)->delete(route('library.tags.destroy', $tag))->assertNotFound();
    $this->actingAs($owner)->patch(route('library.tags.update', $tag), ['name' => 'Laravel'])->assertSessionHasNoErrors();
    $this->actingAs($owner)->delete(route('library.tags.destroy', $tag))->assertSessionHas('status', 'tag-deleted');

    expect(Tag::query()->count())->toBe(0)
        ->and(DB::table('tag_user_transcript')->count())->toBe(0)
        ->and(UserTranscript::query()->whereKey($item->getKey())->exists())->toBeTrue()
        ->and(Transcript::query()->whereKey($item->transcript_id)->exists())->toBeTrue();
});

test('library search covers title channel and tag case insensitively without duplicates or wildcard expansion', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $title = organizedTranscript($user, 'ORGSEARCH01', 'Curso de LARAVEL');
    $channel = organizedTranscript($user, 'ORGSEARCH02', 'Outro vídeo', ['channel' => 'Canal Gregório']);
    $tagged = organizedTranscript($user, 'ORGSEARCH03', 'História antiga');
    $literalPercent = organizedTranscript($user, 'ORGSEARCH04', 'Taxa de 100%');
    $literalUnderscore = organizedTranscript($user, 'ORGSEARCH05', 'Código nome_exato');
    $tagA = $user->tags()->create(['name' => 'Papado']);
    $tagB = $user->tags()->create(['name' => 'papado medieval']);
    $tagged->tags()->attach([$tagA->getKey(), $tagB->getKey()]);
    organizedTranscript($other, 'ORGSEARCH06', 'Laravel secreto');

    foreach ([
        ['q' => 'laravel', 'id' => $title->public_id],
        ['q' => 'GREG', 'id' => $channel->public_id],
        ['q' => 'papado', 'id' => $tagged->public_id],
        ['q' => '%', 'id' => $literalPercent->public_id],
        ['q' => '_', 'id' => $literalUnderscore->public_id],
    ] as $case) {
        $response = $this->actingAs($user)->get(route('library.index', ['q' => $case['q']]))->assertOk();
        expect($response->inertiaProps('library.filters.q'))->toBe($case['q'])
            ->and($response->inertiaProps('library.items'))->toHaveCount(1, "Busca: {$case['q']}");
        $response->assertInertia(fn (Assert $page) => $page->where('library.items.0.publicId', $case['id']));
    }
});

test('library filters and sorts remain owner scoped and preserve the query string in pagination', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $folder = $user->folders()->create(['name' => 'Faculdade']);
    $tag = $user->tags()->create(['name' => 'PHP']);
    $automatic = organizedTranscript($user, 'ORGFILTER01', 'Zulu', ['language' => 'en', 'language_name' => 'English', 'source' => TranscriptSource::Automatic]);
    $automatic->update(['folder_id' => $folder->getKey()]);
    $automatic->tags()->attach($tag);
    $unfiled = organizedTranscript($user, 'ORGFILTER02', 'Alpha');
    organizedTranscript($other, 'ORGFILTER03', 'Other');

    $filters = ['folder' => $folder->public_id, 'tag' => $tag->public_id, 'language' => 'en', 'source' => 'automatic', 'sort' => 'title_asc'];
    $this->actingAs($user)->get(route('library.index', $filters))->assertInertia(fn (Assert $page) => $page
        ->has('library.items', 1)
        ->where('library.items.0.publicId', $automatic->public_id)
        ->where('library.filters', ['q' => '', ...$filters])
    );
    $this->actingAs($user)->get(route('library.index', ['folder' => 'none']))->assertInertia(fn (Assert $page) => $page->has('library.items', 1)->where('library.items.0.publicId', $unfiled->public_id));

    foreach (range(1, 21) as $index) {
        organizedTranscript($user, sprintf('ORGPAGE%04d', $index), sprintf('Página %02d', $index));
    }
    $response = $this->actingAs($user)->get(route('library.index', ['q' => 'Página', 'sort' => 'oldest']));
    $response->assertInertia(fn (Assert $page) => $page
        ->where('library.pagination.lastPage', 2)
        ->where('library.pagination.nextPageUrl', fn (string $url): bool => str_contains($url, 'q=P%C3%A1gina') && str_contains($url, 'sort=oldest'))
    );

    $otherFolder = $other->folders()->create(['name' => 'Privada']);
    $otherTag = $other->tags()->create(['name' => 'Privada']);
    $this->actingAs($user)->get(route('library.index', ['folder' => $otherFolder->public_id]))->assertNotFound();
    $this->actingAs($user)->get(route('library.index', ['tag' => $otherTag->public_id]))->assertNotFound();
});

test('every allowed sort has a stable deterministic order', function () {
    $user = User::factory()->create();
    $zulu = organizedTranscript($user, 'ORGSORT0001', 'Zulu');
    $alpha = organizedTranscript($user, 'ORGSORT0002', 'Alpha');
    $echo = organizedTranscript($user, 'ORGSORT0003', 'Echo');
    $zulu->forceFill(['created_at' => now()->subMinute()])->saveQuietly();
    $echo->forceFill(['created_at' => now()->subMinutes(2)])->saveQuietly();
    $alpha->forceFill(['created_at' => now()->subMinutes(3)])->saveQuietly();

    foreach ([
        'newest' => ['Zulu', 'Echo', 'Alpha'],
        'oldest' => ['Alpha', 'Echo', 'Zulu'],
        'title_asc' => ['Alpha', 'Echo', 'Zulu'],
        'title_desc' => ['Zulu', 'Echo', 'Alpha'],
    ] as $sort => $expected) {
        $response = $this->actingAs($user)->get(route('library.index', ['sort' => $sort]));
        expect(collect($response->inertiaProps('library.items'))->pluck('title')->all())->toBe($expected);
    }
});

test('bulk move resolves every item and folder before an atomic mutation', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $folder = $user->folders()->create(['name' => 'Destino']);
    $otherFolder = $other->folders()->create(['name' => 'Outro destino']);
    $first = organizedTranscript($user, 'ORGMOVE0001', 'Primeiro');
    $second = organizedTranscript($user, 'ORGMOVE0002', 'Segundo');
    $foreign = organizedTranscript($other, 'ORGMOVE0003', 'Terceiro');

    $this->actingAs($user)->patch(route('library.items.move'), ['item_public_ids' => [$first->public_id, $second->public_id], 'folder_public_id' => $folder->public_id])->assertSessionHasNoErrors();
    expect($first->refresh()->folder_id)->toBe($folder->getKey())->and($second->refresh()->folder_id)->toBe($folder->getKey());
    $this->actingAs($user)->patch(route('library.items.move'), ['item_public_ids' => [$first->public_id, $foreign->public_id], 'folder_public_id' => null])->assertNotFound();
    expect($first->refresh()->folder_id)->toBe($folder->getKey());
    $this->actingAs($user)->patch(route('library.items.move'), ['item_public_ids' => [$first->public_id], 'folder_public_id' => $otherFolder->public_id])->assertNotFound();
    expect($first->refresh()->folder_id)->toBe($folder->getKey());
    $this->actingAs($user)->patch(route('library.items.move'), ['item_public_ids' => [$first->public_id, $second->public_id], 'folder_public_id' => null])->assertSessionHasNoErrors();
    expect($first->refresh()->folder_id)->toBeNull()->and($second->refresh()->folder_id)->toBeNull();
});

test('bulk tag add remove and delete are atomic across ownership boundaries', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $first = organizedTranscript($user, 'ORGBULK0001', 'Primeiro');
    $second = organizedTranscript($user, 'ORGBULK0002', 'Segundo');
    $foreign = organizedTranscript($other, 'ORGBULK0003', 'Terceiro');
    $tag = $user->tags()->create(['name' => 'Estudo']);
    $foreignTag = $other->tags()->create(['name' => 'Privada']);
    $items = [$first->public_id, $second->public_id];

    $this->actingAs($user)->post(route('library.items.tags.add'), ['item_public_ids' => $items, 'tag_public_ids' => [$tag->public_id]])->assertSessionHasNoErrors();
    expect(DB::table('tag_user_transcript')->count())->toBe(2);
    $this->actingAs($user)->delete(route('library.items.tags.remove'), ['item_public_ids' => [$first->public_id], 'tag_public_ids' => [$tag->public_id]])->assertSessionHasNoErrors();
    expect($first->tags()->count())->toBe(0)->and($second->tags()->count())->toBe(1);

    $this->actingAs($user)->post(route('library.items.tags.add'), ['item_public_ids' => $items, 'tag_public_ids' => [$tag->public_id, $foreignTag->public_id]])->assertNotFound();
    expect(DB::table('tag_user_transcript')->count())->toBe(1);
    $this->actingAs($user)->delete(route('library.items.destroy'), ['item_public_ids' => [$first->public_id, $foreign->public_id]])->assertNotFound();
    expect(UserTranscript::query()->whereKey($first->getKey())->exists())->toBeTrue();

    TranscriptSegment::query()->create(['transcript_id' => $second->transcript_id, 'position' => 0, 'start_ms' => 0, 'end_ms' => 1000, 'text' => 'Preservado']);
    $videoId = $second->transcript->video_id;
    $this->actingAs($user)->delete(route('library.items.destroy'), ['item_public_ids' => $items])->assertSessionHas('status', 'library-items-removed');
    expect(UserTranscript::query()->whereIn('id', [$first->getKey(), $second->getKey()])->count())->toBe(0)
        ->and(Transcript::query()->whereKey($second->transcript_id)->exists())->toBeTrue()
        ->and(Video::query()->whereKey($videoId)->exists())->toBeTrue()
        ->and(TranscriptSegment::query()->where('transcript_id', $second->transcript_id)->exists())->toBeTrue()
        ->and(Tag::query()->whereKey($tag->getKey())->exists())->toBeTrue();
});

test('bulk requests reject more than one hundred opaque item ids', function () {
    $user = User::factory()->create();
    $ids = collect(range(1, 101))->map(fn (): string => (string) Str::ulid())->all();

    $this->actingAs($user)->patch(route('library.items.move'), ['item_public_ids' => $ids, 'folder_public_id' => null])
        ->assertSessionHasErrors('item_public_ids');
});

test('index payload exposes organization public ids without database ids and avoids per item queries', function () {
    $user = User::factory()->create();
    $folder = $user->folders()->create(['name' => 'Pasta']);
    $tag = $user->tags()->create(['name' => 'Tag']);
    foreach (range(1, 5) as $index) {
        $item = organizedTranscript($user, sprintf('ORGPAY%05d', $index), "Vídeo {$index}");
        $item->update(['folder_id' => $folder->getKey()]);
        $item->tags()->attach($tag);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();
    $response = $this->actingAs($user)->get(route('library.index'));
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    $response->assertInertia(fn (Assert $page) => $page
        ->has('library.items', 5)
        ->has('library.folders', 1)
        ->has('library.tags', 1)
        ->has('library.languages', 1)
        ->where('library.items.0.folder.publicId', $folder->public_id)
        ->where('library.items.0.tags.0.publicId', $tag->public_id)
        ->missing('library.items.0.id')
        ->missing('library.items.0.folder.id')
        ->missing('library.items.0.tags.0.id')
        ->missing('library.folders.0.id')
        ->missing('library.tags.0.id')
    );
    expect($queryCount)->toBeLessThan(15);
});
