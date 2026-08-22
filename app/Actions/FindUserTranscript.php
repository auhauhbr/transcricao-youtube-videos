<?php

namespace App\Actions;

use App\Models\User;
use App\Models\UserTranscript;

final class FindUserTranscript
{
    public function handle(User $user, string $publicId): UserTranscript
    {
        return UserTranscript::query()
            ->where('user_id', $user->getKey())
            ->where('public_id', $publicId)
            ->firstOrFail();
    }
}
