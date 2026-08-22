<?php

namespace App\Actions;

use App\Enums\ExtractionStatus;
use App\Models\Extraction;
use App\Models\GuestUsage;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ClaimGuestExtractions
{
    public function __construct(private readonly EnsureUserTranscript $ensureUserTranscript) {}

    public function handle(User $user, ?GuestUsage $guestUsage): int
    {
        if ($guestUsage === null) {
            return 0;
        }

        return DB::transaction(function () use ($user, $guestUsage): int {
            $extractions = Extraction::query()
                ->where('guest_usage_id', $guestUsage->getKey())
                ->whereNull('user_id')
                ->lockForUpdate()
                ->get(['id', 'status', 'transcript_id']);

            if ($extractions->isEmpty()) {
                return 0;
            }

            Extraction::query()
                ->whereKey($extractions->modelKeys())
                ->update(['user_id' => $user->getKey()]);

            $this->ensureUserTranscript->handleMany(
                (int) $user->getKey(),
                $extractions
                    ->where('status', ExtractionStatus::Ready)
                    ->pluck('transcript_id')
                    ->filter(),
            );

            return $extractions->count();
        }, 3);
    }
}
