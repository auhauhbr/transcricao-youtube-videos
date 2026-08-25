<?php

namespace App\Actions;

use App\Models\User;
use App\Models\UserTranscript;
use Illuminate\Support\Facades\DB;

final class RemoveLibraryItems
{
    public function __construct(private readonly ResolveUserTranscripts $resolveItems) {}

    /** @param array<int, string> $itemPublicIds */
    public function handle(User $user, array $itemPublicIds): int
    {
        $items = $this->resolveItems->handle($user, $itemPublicIds);

        return DB::transaction(fn (): int => UserTranscript::query()->whereKey($items->modelKeys())->delete());
    }
}
