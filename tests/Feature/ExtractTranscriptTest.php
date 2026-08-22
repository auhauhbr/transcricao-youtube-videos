<?php

use App\Models\Video;
use App\Transcript\Contracts\TranscriptProvider;
use App\Transcript\Data\TranscriptData;
use App\Transcript\Exceptions\TranscriptProviderException;
use App\Transcript\Providers\FakeTranscriptProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('a supported YouTube URL renders the transient transcript result', function () {
    Http::preventStrayRequests();

    $this->post(route('transcripts.extract'), [
        'video_url' => 'https://youtu.be/dQw4w9WgXcQ?t=120',
    ])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Transcripts/Show')
            ->where('transcript.video.provider', 'youtube')
            ->where('transcript.video.providerVideoId', 'dQw4w9WgXcQ')
            ->where('transcript.languageCode', 'pt-BR')
            ->has('transcript.segments', 6)
            ->has('transcript.chapters', 3)
            ->where('youtubeUrl', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ')
        );

    $this->assertDatabaseCount('videos', 0);
});

test('invalid YouTube URLs return a field validation error and preserve input', function (string $videoUrl) {
    $this->from('/')
        ->post(route('transcripts.extract'), ['video_url' => $videoUrl])
        ->assertRedirect('/')
        ->assertSessionHasErrors(['video_url' => 'Informe uma URL válida de vídeo do YouTube.'])
        ->assertSessionHasInput('video_url', $videoUrl);
})->with([
    'unsupported host' => ['https://example.com/watch?v=dQw4w9WgXcQ'],
    'malicious lookalike host' => ['https://youtube.com.evil.example/watch?v=dQw4w9WgXcQ'],
]);

test('the extraction request validates required input', function () {
    $this->from('/')
        ->post(route('transcripts.extract'), ['video_url' => ''])
        ->assertRedirect('/')
        ->assertSessionHasErrors(['video_url' => 'Informe a URL de um vídeo do YouTube.']);
});

test('the extraction request limits URL length', function () {
    $this->from('/')
        ->post(route('transcripts.extract'), ['video_url' => str_repeat('a', 2049)])
        ->assertRedirect('/')
        ->assertSessionHasErrors(['video_url' => 'A URL do vídeo não pode ter mais de 2048 caracteres.']);
});

test('provider failures return a safe validation error', function () {
    $this->app->bind(TranscriptProvider::class, fn () => new class implements TranscriptProvider
    {
        public function fetch(string $providerVideoId): TranscriptData
        {
            throw new TranscriptProviderException('Controlled provider failure.');
        }
    });

    $this->from('/')
        ->post(route('transcripts.extract'), [
            'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ])
        ->assertRedirect('/')
        ->assertSessionHasErrors(['video_url' => 'Não foi possível obter a transcrição deste vídeo.']);
});

test('the fake provider is selected through the service container', function () {
    expect($this->app->make(TranscriptProvider::class))->toBeInstanceOf(FakeTranscriptProvider::class);
});

test('a missing provider configuration never resolves silently to fake', function () {
    config(['transcripts.provider' => null]);

    expect(fn () => $this->app->make(TranscriptProvider::class))->toThrow(LogicException::class);
});

test('the public fake workflow creates no transcript domain records', function () {
    $this->post(route('transcripts.extract'), [
        'video_url' => 'https://youtube.com/shorts/dQw4w9WgXcQ',
    ])->assertOk();

    expect(Video::query()->count())->toBe(0)
        ->and(DB::table('transcripts')->count())->toBe(0)
        ->and(DB::table('transcript_segments')->count())->toBe(0)
        ->and(DB::table('chapters')->count())->toBe(0)
        ->and(DB::table('extractions')->count())->toBe(0);
});
