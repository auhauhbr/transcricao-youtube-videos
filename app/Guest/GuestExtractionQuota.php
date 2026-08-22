<?php

namespace App\Guest;

use App\Enums\ExtractionStatus;
use App\Exceptions\AnonymousQuotaExceededException;
use App\Models\Extraction;
use App\Models\GuestUsage;
use Closure;
use Illuminate\Support\Facades\DB;

final class GuestExtractionQuota
{
    /**
     * @template TResult
     *
     * @param  Closure(GuestUsage): TResult  $createExtraction
     * @return TResult
     */
    public function reserve(GuestUsage $guestUsage, Closure $createExtraction): mixed
    {
        return DB::transaction(function () use ($guestUsage, $createExtraction): mixed {
            $lockedUsage = GuestUsage::query()
                ->lockForUpdate()
                ->findOrFail($guestUsage->getKey());

            if ($lockedUsage->used_slots >= $this->limit()) {
                throw new AnonymousQuotaExceededException('The anonymous transcript quota has been exhausted.');
            }

            $lockedUsage->forceFill([
                'used_slots' => $lockedUsage->used_slots + 1,
            ])->save();

            return $createExtraction($lockedUsage);
        }, 3);
    }

    public function releaseFailedExtraction(Extraction $extraction): bool
    {
        return DB::transaction(function () use ($extraction): bool {
            $lockedExtraction = Extraction::query()
                ->lockForUpdate()
                ->findOrFail($extraction->getKey());

            if (
                $lockedExtraction->status !== ExtractionStatus::Failed
                || $lockedExtraction->guest_usage_id === null
                || $lockedExtraction->guest_slot_released_at !== null
            ) {
                return false;
            }

            $lockedUsage = GuestUsage::query()
                ->lockForUpdate()
                ->find($lockedExtraction->guest_usage_id);

            if ($lockedUsage !== null) {
                $lockedUsage->forceFill([
                    'used_slots' => max(0, $lockedUsage->used_slots - 1),
                ])->save();
            }

            $lockedExtraction->forceFill([
                'guest_slot_released_at' => now(),
            ])->save();

            return true;
        }, 3);
    }

    /** @return array{limit: int, used: int, remaining: int} */
    public function summary(GuestUsage $guestUsage): array
    {
        $used = min($this->limit(), max(0, $guestUsage->used_slots));

        return [
            'limit' => $this->limit(),
            'used' => $used,
            'remaining' => max(0, $this->limit() - $used),
        ];
    }

    public function limit(): int
    {
        return max(1, (int) config('transcripts.anonymous.limit'));
    }
}
