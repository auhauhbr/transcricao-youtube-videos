<?php

namespace App\Models;

use App\Enums\ChapterSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['transcript_id', 'position', 'title', 'start_ms', 'end_ms', 'source'])]
class Chapter extends Model
{
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'start_ms' => 'integer',
            'end_ms' => 'integer',
            'source' => ChapterSource::class,
        ];
    }

    /** @return BelongsTo<Transcript, $this> */
    public function transcript(): BelongsTo
    {
        return $this->belongsTo(Transcript::class);
    }
}
