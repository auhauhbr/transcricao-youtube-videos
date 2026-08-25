<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** @property string $public_id */
#[Fillable(['user_id', 'name'])]
class Folder extends Model
{
    protected static function booted(): void
    {
        static::creating(function (Folder $folder): void {
            $folder->public_id ??= (string) Str::ulid();
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

    /** @return HasMany<UserTranscript, $this> */
    public function userTranscripts(): HasMany
    {
        return $this->hasMany(UserTranscript::class);
    }
}
