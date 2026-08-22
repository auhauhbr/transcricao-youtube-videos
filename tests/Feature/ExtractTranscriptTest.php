<?php

use App\Enums\ExtractionStatus;
use App\Jobs\ExtractTranscriptJob;
use App\Models\Extraction;
use App\Models\User;
use App\Models\Video;
use App\Transcript\Contracts\TranscriptProvider;
use Illuminate\Cache\RateLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['cache.default' => 'array']);
    Queue::fake();

    $rateLimiter = app(RateLimiter::class);

    foreach (['127.0.0.1', '203.0.113.10'] as $ip) {
        $rateLimiter->clear(md5('transcript-extractionsminute:'.$ip));
        $rateLimiter->clear(md5('transcript-extractionshour:'.$ip));
    }
});

test('a supported YouTube URL requests an asynchronous extraction and redirects to its public page', function () {
    $provider = Mockery::mock(TranscriptProvider::class);
    $provider->shouldNotReceive('fetch');
    $this->app->instance(TranscriptProvider::class, $provider);

    $response = $this->post(route('transcripts.extract'), [
        'video_url' => 'https://youtu.be/dQw4w9WgXcQ?t=120',
    ]);

    $extraction = Extraction::query()->sole();

    $response->assertRedirect(route('extractions.show', $extraction));

    expect($extraction->status)->toBe(ExtractionStatus::Pending)
        ->and($extraction->public_id)->toHaveLength(26)
        ->and($extraction->transcript_id)->toBeNull()
        ->and($extraction->video->provider_video_id)->toBe('dQw4w9WgXcQ');

    Queue::assertPushedOn(
        'transcripts',
        ExtractTranscriptJob::class,
        fn (ExtractTranscriptJob $job): bool => $job->extractionId === $extraction->getKey(),
    );
});

test('the public request reuses the canonical video through the extraction action', function () {
    $video = Video::factory()->create(['provider_video_id' => 'dQw4w9WgXcQ']);

    $this->post(route('transcripts.extract'), [
        'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    ])->assertRedirect();

    expect(Video::query()->count())->toBe(1)
        ->and(Extraction::query()->sole()->video_id)->toBe($video->getKey());
});

test('invalid extraction requests create no extraction and dispatch no job', function (array $payload, string $message, ?string $oldInput) {
    $response = $this->from('/')->post(route('transcripts.extract'), $payload)
        ->assertRedirect('/')
        ->assertSessionHasErrors(['video_url' => $message]);

    if ($oldInput !== null) {
        $response->assertSessionHasInput('video_url', $oldInput);
    }

    expect(Extraction::query()->count())->toBe(0)
        ->and(Video::query()->count())->toBe(0);
    Queue::assertNothingPushed();
})->with([
    'invalid URL' => [
        ['video_url' => 'not a URL'],
        'Informe uma URL válida de vídeo do YouTube.',
        'not a URL',
    ],
    'malicious lookalike host' => [
        ['video_url' => 'https://youtube.com.evil.example/watch?v=dQw4w9WgXcQ'],
        'Informe uma URL válida de vídeo do YouTube.',
        'https://youtube.com.evil.example/watch?v=dQw4w9WgXcQ',
    ],
    'missing URL' => [
        [],
        'Informe a URL de um vídeo do YouTube.',
        null,
    ],
    'URL over the maximum length' => [
        ['video_url' => str_repeat('a', 2049)],
        'A URL do vídeo não pode ter mais de 2048 caracteres.',
        str_repeat('a', 2049),
    ],
]);

test('normal requests within the burst limit continue to work', function () {
    $this->actingAs(User::factory()->create());

    foreach (range(1, 5) as $requestNumber) {
        $this->post(route('transcripts.extract'), [
            'video_url' => 'https://youtu.be/dQw4w9WgXcQ',
        ])->assertRedirect();
    }

    expect(Extraction::query()->count())->toBe(5);
    Queue::assertPushed(ExtractTranscriptJob::class, 5);
});

test('the public extraction endpoint is rate limited by IP', function () {
    $this->actingAs(User::factory()->create());

    foreach (range(1, 5) as $requestNumber) {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->post(route('transcripts.extract'), [
                'video_url' => 'https://youtu.be/dQw4w9WgXcQ',
            ])->assertRedirect();
    }

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
        ->post(route('transcripts.extract'), [
            'video_url' => 'https://youtu.be/dQw4w9WgXcQ',
        ])
        ->assertTooManyRequests()
        ->assertDontSee('Stack trace');

    expect(Extraction::query()->count())->toBe(5);
    Queue::assertPushed(ExtractTranscriptJob::class, 5);
});
