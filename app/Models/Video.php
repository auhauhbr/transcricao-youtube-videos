<?php

namespace App\Models;

use App\Enums\VideoProvider;
use Database\Factories\VideoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property VideoProvider $provider
 * @property string $provider_video_id
 */
#[Fillable([
    'provider',
    'provider_video_id',
    'title',
    'channel_name',
    'channel_id',
    'thumbnail_url',
    'duration_seconds',
    'published_at',
    'metadata',
])]
class Video extends Model
{
    /** @use HasFactory<VideoFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => VideoProvider::class,
            'duration_seconds' => 'integer',
            'published_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /** @return HasMany<Transcript, $this> */
    public function transcripts(): HasMany
    {
        return $this->hasMany(Transcript::class);
    }

    /** @return HasMany<Extraction, $this> */
    public function extractions(): HasMany
    {
        return $this->hasMany(Extraction::class);
    }
}
