<?php

namespace App\Models;

use App\Enums\ExtractionErrorCode;
use App\Enums\ExtractionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use LogicException;

/**
 * @property ExtractionStatus $status
 * @property ExtractionErrorCode|null $error_code
 * @property int|null $transcript_id
 * @property string $public_id
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property-read Video $video
 */
#[Fillable([
    'user_id',
    'video_id',
    'requested_language',
])]
class Extraction extends Model
{
    protected static function booted(): void
    {
        static::creating(function (Extraction $extraction): void {
            $extraction->public_id ??= (string) Str::ulid();
            $extraction->status ??= ExtractionStatus::Pending;
        });
    }

    protected function casts(): array
    {
        return [
            'status' => ExtractionStatus::class,
            'error_code' => ExtractionErrorCode::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
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

    /** @return BelongsTo<Video, $this> */
    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    /** @return BelongsTo<Transcript, $this> */
    public function transcript(): BelongsTo
    {
        return $this->belongsTo(Transcript::class);
    }

    public function markProcessing(): void
    {
        $this->assertStatus(ExtractionStatus::Pending);

        $this->forceFill([
            'status' => ExtractionStatus::Processing,
            'started_at' => now(),
            'completed_at' => null,
            'error_code' => null,
            'error_message' => null,
        ])->save();
    }

    public function markReady(Transcript $transcript): void
    {
        $this->assertStatus(ExtractionStatus::Processing);

        $this->forceFill([
            'status' => ExtractionStatus::Ready,
            'transcript_id' => $transcript->getKey(),
            'completed_at' => now(),
            'error_code' => null,
            'error_message' => null,
        ])->save();
    }

    public function markFailed(ExtractionErrorCode $code, string $message): void
    {
        $this->assertStatus(ExtractionStatus::Processing);

        $this->forceFill([
            'status' => ExtractionStatus::Failed,
            'completed_at' => now(),
            'error_code' => $code,
            'error_message' => $message,
        ])->save();
    }

    private function assertStatus(ExtractionStatus $expected): void
    {
        if ($this->status !== $expected) {
            throw new LogicException("Extraction cannot transition from {$this->status->value}; expected {$expected->value}.");
        }
    }
}
