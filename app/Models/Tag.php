<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/** @property string $public_id */
#[Fillable(['user_id', 'name'])]
class Tag extends Model
{
    protected static function booted(): void
    {
        static::creating(function (Tag $tag): void {
            $tag->public_id ??= (string) Str::ulid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsToMany<UserTranscript, $this> */
    public function userTranscripts(): BelongsToMany
    {
        return $this->belongsToMany(UserTranscript::class);
    }
}
