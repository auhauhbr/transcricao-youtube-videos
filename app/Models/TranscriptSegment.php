<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['transcript_id', 'position', 'start_ms', 'end_ms', 'text'])]
class TranscriptSegment extends Model
{
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'start_ms' => 'integer',
            'end_ms' => 'integer',
        ];
    }

    /** @return BelongsTo<Transcript, $this> */
    public function transcript(): BelongsTo
    {
        return $this->belongsTo(Transcript::class);
    }
}
