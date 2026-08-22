<?php

namespace App\Actions;

use App\Models\Extraction;
use App\Models\GuestUsage;
use App\Models\User;

final class ClaimGuestExtractions
{
    public function handle(User $user, ?GuestUsage $guestUsage): int
    {
        if ($guestUsage === null) {
            return 0;
        }

        return Extraction::query()
            ->where('guest_usage_id', $guestUsage->getKey())
            ->whereNull('user_id')
            ->update(['user_id' => $user->getKey()]);
    }
}
