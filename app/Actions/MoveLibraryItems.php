<?php

namespace App\Actions;

use App\Models\Folder;
use App\Models\User;
use App\Models\UserTranscript;
use Illuminate\Support\Facades\DB;

final class MoveLibraryItems
{
    public function __construct(private readonly ResolveUserTranscripts $resolveItems) {}

    /** @param array<int, string> $itemPublicIds */
    public function handle(User $user, array $itemPublicIds, ?string $folderPublicId): int
    {
        $items = $this->resolveItems->handle($user, $itemPublicIds);
        $folder = $folderPublicId === null ? null : Folder::query()
            ->where('user_id', $user->getKey())
            ->where('public_id', $folderPublicId)
            ->firstOrFail();

        DB::transaction(fn (): int => UserTranscript::query()
            ->whereKey($items->modelKeys())
            ->update(['folder_id' => $folder?->getKey()]));

        return $items->count();
    }
}
