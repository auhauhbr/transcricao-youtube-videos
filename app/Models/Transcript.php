<?php

namespace App\Models;

use App\Enums\TranscriptSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property TranscriptSource $source */
#[Fillable([
    'video_id',
    'language_code',
    'language_name',
    'source',
    'word_count',
    'character_count',
    'extracted_at',
])]
class Transcript extends Model
{
    protected function casts(): array
    {
        return [
            'source' => TranscriptSource::class,
            'word_count' => 'integer',
            'character_count' => 'integer',
            'extracted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Video, $this> */
    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    /** @return HasMany<TranscriptSegment, $this> */
    public function segments(): HasMany
    {
        return $this->hasMany(TranscriptSegment::class)->orderBy('position');
    }

    /** @return HasMany<Chapter, $this> */
    public function chapters(): HasMany
    {
        return $this->hasMany(Chapter::class)->orderBy('position');
    }

    /** @return HasMany<Extraction, $this> */
    public function extractions(): HasMany
    {
        return $this->hasMany(Extraction::class);
    }

    /** @return HasMany<UserTranscript, $this> */
    public function userTranscripts(): HasMany
    {
        return $this->hasMany(UserTranscript::class);
    }
}
