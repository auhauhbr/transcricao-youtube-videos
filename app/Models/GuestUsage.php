<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['token_hash', 'used_slots'])]
#[Hidden(['token_hash'])]
class GuestUsage extends Model
{
    protected function casts(): array
    {
        return [
            'used_slots' => 'integer',
        ];
    }

    /** @return HasMany<Extraction, $this> */
    public function extractions(): HasMany
    {
        return $this->hasMany(Extraction::class);
    }
}
