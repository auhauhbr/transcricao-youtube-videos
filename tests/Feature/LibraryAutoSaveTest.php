<?php

use App\Actions\ClaimGuestExtractions;
use App\Actions\EnsureUserTranscript;
use App\Actions\FailTranscriptExtraction;
use App\Actions\PersistTranscriptData;
use App\Enums\ExtractionErrorCode;
use App\Enums\TranscriptSource;
use App\Models\Extraction;
use App\Models\GuestUsage;
use App\Models\Transcript;
use App\Models\User;
use App\Models\UserTranscript;
use App\Models\Video;
use App\Transcript\Providers\FakeTranscriptProvider;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function transcriptForLibraryAutoSave(string $videoId, string $languageCode = 'pt-BR'): Transcript
{
    $video = Video::factory()->create([
        'provider_video_id' => $videoId,
        'title' => "Vídeo {$videoId}",
    ]);

    return Transcript::query()->create([
        'video_id' => $video->getKey(),
        'language_code' => $languageCode,
        'language_name' => 'Português',
        'source' => TranscriptSource::Manual,
        'word_count' => 2,
        'character_count' => 12,
        'extracted_at' => now(),
    ]);
}

function extractionWithOwner(?User $user, Video $video): Extraction
{
    $extraction = new Extraction;
    $extraction->video()->associate($video);
    $extraction->user()->associate($user);
    $extraction->save();

    return $extraction;
}

test('an authenticated extraction is saved only when transcript finalization reaches ready', function () {
    $user = User::factory()->create();
    $extraction = extractionWithOwner($user, Video::factory()->create(['provider_video_id' => 'dQw4w9WgXcQ']));

    expect(UserTranscript::query()->count())->toBe(0);
    $extraction->markProcessing();
    expect(UserTranscript::query()->count())->toBe(0);

    $transcript = app(PersistTranscriptData::class)->handle(
        $extraction,
        (new FakeTranscriptProvider)->fetch('dQw4w9WgXcQ'),
    );

    $item = UserTranscript::query()->sole();
    expect($item->user_id)->toBe($user->getKey())
        ->and($item->transcript_id)->toBe($transcript->getKey())
        ->and($extraction->refresh()->transcript_id)->toBe($transcript->getKey());
});

test('failed authenticated extractions never enter the library', function () {
    $user = User::factory()->create();
    $extraction = extractionWithOwner($user, Video::factory()->create());
    $extraction->markProcessing();

    app(FailTranscriptExtraction::class)->handle(
        $extraction,
        ExtractionErrorCode::ProviderError,
        'Safe failure.',
    );

    expect(UserTranscript::query()->count())->toBe(0);
});

test('two finalizations and repeated ensures create one item for the same user and transcript', function () {
    $user = User::factory()->create();
    $video = Video::factory()->create(['provider_video_id' => 'dQw4w9WgXcQ']);
    $first = extractionWithOwner($user, $video);
    $second = extractionWithOwner($user, $video);
    $first->markProcessing();
    $second->markProcessing();
    $data = (new FakeTranscriptProvider)->fetch('dQw4w9WgXcQ');

    $firstTranscript = app(PersistTranscriptData::class)->handle($first, $data);
    $secondTranscript = app(PersistTranscriptData::class)->handle($second, $data);
    app(EnsureUserTranscript::class)->handle($user->getKey(), $firstTranscript->getKey());
    app(EnsureUserTranscript::class)->handle($user->getKey(), $firstTranscript->getKey());

    expect($firstTranscript->getKey())->toBe($secondTranscript->getKey())
        ->and(UserTranscript::query()->count())->toBe(1);
});

test('the database enforces one item per user and transcript', function () {
    $user = User::factory()->create();
    $transcript = transcriptForLibraryAutoSave('LIBAUTO0001');
    app(EnsureUserTranscript::class)->handle($user->getKey(), $transcript->getKey());

    expect(fn () => UserTranscript::query()->create([
        'user_id' => $user->getKey(),
        'transcript_id' => $transcript->getKey(),
    ]))->toThrow(QueryException::class);

    expect(UserTranscript::query()->count())->toBe(1);
});

test('claiming ready guest extractions adds only distinct transcripts to the library', function () {
    $guestUsage = GuestUsage::query()->create([
        'token_hash' => hash('sha256', 'library-ready-guest'),
        'used_slots' => 3,
    ]);
    $firstTranscript = transcriptForLibraryAutoSave('LIBREADY001');
    $secondTranscript = transcriptForLibraryAutoSave('LIBREADY002');

    foreach ([$firstTranscript, $firstTranscript, $secondTranscript] as $transcript) {
        $extraction = new Extraction;
        $extraction->video()->associate($transcript->video);
        $extraction->guestUsage()->associate($guestUsage);
        $extraction->save();
        $extraction->markProcessing();
        $extraction->markReady($transcript);
    }

    $user = User::factory()->create();
    $claimed = app(ClaimGuestExtractions::class)->handle($user, $guestUsage);

    expect($claimed)->toBe(3)
        ->and(Extraction::query()->where('user_id', $user->getKey())->count())->toBe(3)
        ->and(UserTranscript::query()->where('user_id', $user->getKey())->count())->toBe(2)
        ->and(UserTranscript::query()->pluck('transcript_id')->sort()->values()->all())
        ->toBe([$firstTranscript->getKey(), $secondTranscript->getKey()]);
});

test('an adopted processing extraction enters the library when it later finishes', function () {
    $guestUsage = GuestUsage::query()->create([
        'token_hash' => hash('sha256', 'library-processing-guest'),
        'used_slots' => 1,
    ]);
    $video = Video::factory()->create(['provider_video_id' => 'dQw4w9WgXcQ']);
    $extraction = new Extraction;
    $extraction->video()->associate($video);
    $extraction->guestUsage()->associate($guestUsage);
    $extraction->save();
    $extraction->markProcessing();
    $user = User::factory()->create();

    app(ClaimGuestExtractions::class)->handle($user, $guestUsage);
    expect(UserTranscript::query()->count())->toBe(0);

    $transcript = app(PersistTranscriptData::class)->handle(
        $extraction,
        (new FakeTranscriptProvider)->fetch('dQw4w9WgXcQ'),
    );

    expect($extraction->refresh()->user_id)->toBe($user->getKey())
        ->and(UserTranscript::query()->sole()->transcript_id)->toBe($transcript->getKey());
});

test('claiming a failed guest extraction does not create a library item', function () {
    $guestUsage = GuestUsage::query()->create([
        'token_hash' => hash('sha256', 'library-failed-guest'),
        'used_slots' => 0,
    ]);
    $extraction = new Extraction;
    $extraction->video()->associate(Video::factory()->create());
    $extraction->guestUsage()->associate($guestUsage);
    $extraction->save();
    $extraction->markProcessing();
    $extraction->markFailed(ExtractionErrorCode::ProviderError, 'Safe failure.');
    $user = User::factory()->create();

    app(ClaimGuestExtractions::class)->handle($user, $guestUsage);

    expect($extraction->refresh()->user_id)->toBe($user->getKey())
        ->and(UserTranscript::query()->count())->toBe(0);
});

test('a removed transcript can be added to the same library again', function () {
    $user = User::factory()->create();
    $transcript = transcriptForLibraryAutoSave('LIBREADD001');
    $first = app(EnsureUserTranscript::class)->handle($user->getKey(), $transcript->getKey());
    $firstPublicId = $first->public_id;
    $first->delete();

    $second = app(EnsureUserTranscript::class)->handle($user->getKey(), $transcript->getKey());

    expect(UserTranscript::query()->count())->toBe(1)
        ->and($second->public_id)->not->toBe($firstPublicId)
        ->and($second->transcript_id)->toBe($transcript->getKey());
});
