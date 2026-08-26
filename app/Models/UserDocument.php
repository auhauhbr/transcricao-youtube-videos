<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property string $public_id
 * @property array<string, mixed> $content
 * @property int $lock_version
 */
#[Fillable(['user_transcript_id', 'title', 'content', 'lock_version'])]
class UserDocument extends Model
{
    protected static function booted(): void
    {
        static::creating(function (UserDocument $document): void {
            $document->public_id ??= (string) Str::ulid();
        });
    }

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'lock_version' => 'integer',
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
