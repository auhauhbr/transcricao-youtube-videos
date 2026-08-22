<?php

use App\Actions\EnsureUserTranscript;
use App\Enums\ChapterSource;
use App\Enums\TranscriptSource;
use App\Models\Chapter;
use App\Models\Extraction;
use App\Models\Transcript;
use App\Models\TranscriptSegment;
use App\Models\User;
use App\Models\UserTranscript;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function transcriptForLibrary(string $videoId, string $title = 'Vídeo da biblioteca'): Transcript
{
    $video = Video::factory()->create([
        'provider_video_id' => $videoId,
        'title' => $title,
        'channel_name' => 'Canal da biblioteca',
        'channel_id' => 'UC-library',
        'thumbnail_url' => "https://i.ytimg.com/vi/{$videoId}/hqdefault.jpg",
        'duration_seconds' => 95,
    ]);
    $transcript = Transcript::query()->create([
        'video_id' => $video->getKey(),
        'language_code' => 'pt-BR',
        'language_name' => 'Português',
        'source' => TranscriptSource::Manual,
        'word_count' => 4,
        'character_count' => 29,
        'extracted_at' => now(),
    ]);

    foreach ([
        ['position' => 1, 'start_ms' => 30_000, 'end_ms' => 60_000, 'text' => 'Segundo bloco completo.'],
        ['position' => 0, 'start_ms' => 0, 'end_ms' => 30_000, 'text' => 'Primeiro bloco completo.'],
    ] as $segment) {
        TranscriptSegment::query()->create(['transcript_id' => $transcript->getKey(), ...$segment]);
    }

    Chapter::query()->create([
        'transcript_id' => $transcript->getKey(),
        'position' => 0,
        'title' => 'Capítulo principal',
        'start_ms' => 0,
        'end_ms' => 60_000,
        'source' => ChapterSource::Provider,
    ]);

    return $transcript;
}

function addTranscriptToLibrary(User $user, Transcript $transcript): UserTranscript
{
    return app(EnsureUserTranscript::class)->handle($user->getKey(), $transcript->getKey());
}

test('guests are redirected from every private library endpoint', function () {
    $user = User::factory()->create();
    $item = addTranscriptToLibrary($user, transcriptForLibrary('LIBGUEST001'));
    $query = ['format' => 'txt', 'mode' => 'formatted', 'timestamps' => '1'];

    $this->get(route('library.index'))->assertRedirect(route('login'));
    $this->get(route('library.show', $item))->assertRedirect(route('login'));
    $this->get(route('library.download', [$item, ...$query]))->assertRedirect(route('login'));
    $this->delete(route('library.destroy', $item))->assertRedirect(route('login'));
});

test('an empty private library renders a compact empty state payload', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('library.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Library/Index')
            ->where('library.items', [])
            ->where('library.pagination.total', 0)
            ->where('library.pagination.currentPage', 1)
        );
});

test('the library is paginated newest first without loading transcript contents or another user items', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    foreach (range(0, 21) as $index) {
        $transcript = transcriptForLibrary(sprintf('LIB%08d', $index), sprintf('Vídeo %02d', $index));
        $item = addTranscriptToLibrary($user, $transcript);
        $item->forceFill(['created_at' => now()->subMinutes($index)])->saveQuietly();
    }

    addTranscriptToLibrary($other, transcriptForLibrary('OTHER000001', 'Não pode aparecer'));

    DB::flushQueryLog();
    DB::enableQueryLog();
    $firstPage = $this->actingAs($user)->get(route('library.index'));
    $queries = collect(DB::getQueryLog())->pluck('query');
    DB::disableQueryLog();

    $firstPage->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Library/Index')
        ->has('library.items', 20)
        ->where('library.items.0.title', 'Vídeo 00')
        ->where('library.items.0.sourceLabel', 'Legendas manuais')
        ->where('library.pagination.currentPage', 1)
        ->where('library.pagination.lastPage', 2)
        ->where('library.pagination.perPage', 20)
        ->where('library.pagination.total', 22)
        ->missing('library.items.0.id')
        ->missing('library.items.0.user_id')
        ->missing('library.items.0.transcript_id')
        ->missing('library.items.0.segments')
        ->missing('library.items.0.chapters')
    );

    expect($queries->contains(fn (string $query): bool => str_contains($query, 'transcript_segments')))->toBeFalse()
        ->and($queries->contains(fn (string $query): bool => str_contains($query, 'chapters')))->toBeFalse();

    $this->actingAs($user)->get(route('library.index', ['page' => 2]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('library.items', 2)
            ->where('library.items.0.title', 'Vídeo 20')
            ->where('library.items.1.title', 'Vídeo 21')
            ->where('library.pagination.currentPage', 2)
            ->where('library.pagination.total', 22)
        );

    $firstPage->assertDontSee('Não pode aparecer');
});

test('only the owner can show download or remove a library item', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $item = addTranscriptToLibrary($owner, transcriptForLibrary('LIBOWNER01A'));
    $query = ['format' => 'txt', 'mode' => 'formatted', 'timestamps' => '1'];

    $this->actingAs($owner)->get(route('library.show', $item))->assertOk();
    $this->actingAs($owner)->get(route('library.download', [$item, ...$query]))->assertOk();

    $this->actingAs($other)->get(route('library.show', $item))->assertNotFound();
    $this->actingAs($other)->get(route('library.download', [$item, ...$query]))->assertNotFound();
    $this->actingAs($other)->delete(route('library.destroy', $item))->assertNotFound();

    expect(UserTranscript::query()->whereKey($item->getKey())->exists())->toBeTrue();
});

test('library show reuses the public transcript result payload without internal identifiers', function () {
    $user = User::factory()->create();
    $item = addTranscriptToLibrary($user, transcriptForLibrary('LIBSHOW0001', 'Leitura privada'));

    $this->actingAs($user)->get(route('library.show', $item))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Library/Show')
            ->where('video.providerVideoId', 'LIBSHOW0001')
            ->where('video.title', 'Leitura privada')
            ->where('video.youtubeUrl', 'https://www.youtube.com/watch?v=LIBSHOW0001')
            ->where('transcript.languageCode', 'pt-BR')
            ->where('transcript.source', 'manual')
            ->where('transcript.sourceLabel', 'Legendas manuais')
            ->where('transcript.blocks.0.text', 'Primeiro bloco completo.')
            ->where('transcript.blocks.1.text', 'Segundo bloco completo.')
            ->where('transcript.chapters.0.title', 'Capítulo principal')
            ->where('downloadUrl', "/library/{$item->public_id}/download")
            ->where('backUrl', '/library')
            ->missing('video.id')
            ->missing('transcript.id')
            ->missing('transcript.segments')
            ->missing('user_id')
        );
});

test('library download reuses the transcript exporter with owner authorization', function () {
    $user = User::factory()->create();
    $item = addTranscriptToLibrary($user, transcriptForLibrary('LIBDOWN0001', 'Arquivo privado'));

    $response = $this->actingAs($user)->get(route('library.download', [
        'userTranscript' => $item,
        'format' => 'md',
        'mode' => 'segmented',
        'timestamps' => '0',
    ]));

    $response->assertOk();
    expect($response->headers->get('Content-Disposition'))->toContain('arquivo-privado.md')
        ->and($response->headers->get('Content-Type'))->toBe('text/markdown; charset=UTF-8')
        ->and($response->headers->get('Cache-Control'))->toContain('private')->toContain('no-store')
        ->and($response->streamedContent())->toContain('Primeiro bloco completo.')->not->toContain('00:00');
});

test('removing a library item preserves every global transcript resource and extraction', function () {
    $user = User::factory()->create();
    $transcript = transcriptForLibrary('LIBDELETE01');
    $item = addTranscriptToLibrary($user, $transcript);
    $extraction = Extraction::query()->create([
        'user_id' => $user->getKey(),
        'video_id' => $transcript->video_id,
    ]);
    $extraction->markProcessing();
    $extraction->markReady($transcript);

    $this->actingAs($user)->delete(route('library.destroy', $item))
        ->assertRedirect(route('library.index'))
        ->assertSessionHas('status', 'library-item-removed');

    expect(UserTranscript::query()->whereKey($item->getKey())->exists())->toBeFalse()
        ->and(Transcript::query()->whereKey($transcript->getKey())->exists())->toBeTrue()
        ->and(TranscriptSegment::query()->where('transcript_id', $transcript->getKey())->count())->toBe(2)
        ->and(Chapter::query()->where('transcript_id', $transcript->getKey())->count())->toBe(1)
        ->and(Video::query()->whereKey($transcript->video_id)->exists())->toBeTrue()
        ->and(Extraction::query()->whereKey($extraction->getKey())->exists())->toBeTrue();
});

test('library routes accept only owned opaque ULIDs instead of sequential ids', function () {
    $user = User::factory()->create();
    $item = addTranscriptToLibrary($user, transcriptForLibrary('LIBULID0001'));

    expect(Str::isUlid($item->public_id))->toBeTrue();
    $this->actingAs($user)->get(route('library.show', $item))->assertOk();
    $this->actingAs($user)->get('/library/'.$item->getKey())->assertNotFound();
    $this->actingAs($user)->get('/library/01K37XW6ZA8XZPB4XWYSP8ZKXY')->assertNotFound();
    $this->actingAs($user)->get('/library/not-an-ulid')->assertNotFound();
});

test('an authenticated ready extraction links to its private library item while a guest extraction does not', function () {
    $user = User::factory()->create();
    $transcript = transcriptForLibrary('LIBLINK0001');
    $item = addTranscriptToLibrary($user, $transcript);
    $extraction = Extraction::query()->create([
        'user_id' => $user->getKey(),
        'video_id' => $transcript->video_id,
    ]);
    $extraction->markProcessing();
    $extraction->markReady($transcript);

    $this->actingAs($user)->get(route('extractions.show', $extraction))
        ->assertInertia(fn (Assert $page) => $page
            ->where('libraryUrl', "/library/{$item->public_id}")
        );

    Auth::logout();

    $this->get(route('extractions.show', $extraction))
        ->assertInertia(fn (Assert $page) => $page->where('libraryUrl', null));
});
