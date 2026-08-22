<?php

use App\Enums\VideoProvider;
use App\Models\Video;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

test('a video can be persisted with its provider identity', function () {
    $video = Video::factory()->create([
        'provider' => VideoProvider::YouTube,
        'provider_video_id' => 'dQw4w9WgXcQ',
    ]);

    expect($video)
        ->provider->toBe(VideoProvider::YouTube)
        ->provider_video_id->toBe('dQw4w9WgXcQ')
        ->title->toBeNull()
        ->channel_name->toBeNull();

    $this->assertDatabaseHas('videos', [
        'provider' => VideoProvider::YouTube->value,
        'provider_video_id' => 'dQw4w9WgXcQ',
    ]);
});

test('video attributes use the expected casts', function () {
    $video = Video::factory()->create([
        'duration_seconds' => 212,
        'published_at' => '2009-10-25 06:57:33+00:00',
        'metadata' => ['source' => ['language' => 'en']],
    ])->refresh();

    expect($video)
        ->provider->toBeInstanceOf(VideoProvider::class)
        ->duration_seconds->toBeInt()->toBe(212)
        ->published_at->toBeInstanceOf(Carbon::class)
        ->metadata->toBe(['source' => ['language' => 'en']]);
});

test('provider and provider video id are unique together', function () {
    Video::factory()->create([
        'provider' => VideoProvider::YouTube,
        'provider_video_id' => 'dQw4w9WgXcQ',
    ]);

    expect(fn () => Video::factory()->create([
        'provider' => VideoProvider::YouTube,
        'provider_video_id' => 'dQw4w9WgXcQ',
    ]))->toThrow(QueryException::class);
});
