<?php

namespace App\Actions;

use App\Models\User;
use App\Models\UserTranscript;
use Illuminate\Database\Eloquent\Collection;

final class ResolveUserTranscripts
{
    /**
     * @param  array<int, string>  $publicIds
     * @return Collection<int, UserTranscript>
     */
    public function handle(User $user, array $publicIds): Collection
    {
        $items = UserTranscript::query()
            ->where('user_id', $user->getKey())
            ->whereIn('public_id', $publicIds)
            ->get();

        abort_if($items->count() !== count($publicIds), 404);

        return $items;
    }
}
