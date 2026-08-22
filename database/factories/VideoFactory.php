<?php

namespace Database\Factories;

use App\Enums\VideoProvider;
use App\Models\Video;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Video>
 */
class VideoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider' => VideoProvider::YouTube,
            'provider_video_id' => Str::random(11),
            'title' => null,
            'channel_name' => null,
            'channel_id' => null,
            'thumbnail_url' => null,
            'duration_seconds' => null,
            'published_at' => null,
            'metadata' => null,
        ];
    }
}
