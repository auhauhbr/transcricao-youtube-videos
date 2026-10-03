<?php

namespace App\Models;

use App\Enums\UserTranscriptAnnotationType;
use Database\Factories\UserTranscriptAnnotationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property string $public_id
 * @property UserTranscriptAnnotationType $type
 * @property int $start_ms
 * @property string|null $text
 */
#[Fillable(['user_transcript_id', 'start_ms', 'type', 'text'])]
class UserTranscriptAnnotation extends Model
{
    /** @use HasFactory<UserTranscriptAnnotationFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (UserTranscriptAnnotation $annotation): void {
            $annotation->public_id ??= (string) Str::ulid();
        });
    }

    protected function casts(): array
    {
        return [
            'start_ms' => 'integer',
            'type' => UserTranscriptAnnotationType::class,
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /** @return BelongsTo<UserTranscript, $this> */
    public function userTranscript(): BelongsTo
    {
        return $this->belongsTo(UserTranscript::class);
    }
}
