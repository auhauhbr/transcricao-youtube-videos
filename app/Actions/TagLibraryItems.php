<?php

namespace App\Actions;

use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class TagLibraryItems
{
    public function __construct(private readonly ResolveUserTranscripts $resolveItems) {}

    /**
     * @param  array<int, string>  $itemPublicIds
     * @param  array<int, string>  $tagPublicIds
     */
    public function add(User $user, array $itemPublicIds, array $tagPublicIds): int
    {
        [$itemIds, $tagIds] = $this->resolve($user, $itemPublicIds, $tagPublicIds);
        $rows = [];

        foreach ($itemIds as $itemId) {
            foreach ($tagIds as $tagId) {
                $rows[] = ['user_transcript_id' => $itemId, 'tag_id' => $tagId];
            }
        }

        return DB::transaction(fn (): int => DB::table('tag_user_transcript')->insertOrIgnore($rows));
    }

    /**
     * @param  array<int, string>  $itemPublicIds
     * @param  array<int, string>  $tagPublicIds
     */
    public function remove(User $user, array $itemPublicIds, array $tagPublicIds): int
    {
        [$itemIds, $tagIds] = $this->resolve($user, $itemPublicIds, $tagPublicIds);

        return DB::transaction(fn (): int => DB::table('tag_user_transcript')
            ->whereIn('user_transcript_id', $itemIds)
            ->whereIn('tag_id', $tagIds)
            ->delete());
    }

    /**
     * @param  array<int, string>  $itemPublicIds
     * @param  array<int, string>  $tagPublicIds
     * @return array{array<int, int>, array<int, int>}
     */
    private function resolve(User $user, array $itemPublicIds, array $tagPublicIds): array
    {
        $items = $this->resolveItems->handle($user, $itemPublicIds);
        $tags = Tag::query()
            ->where('user_id', $user->getKey())
            ->whereIn('public_id', $tagPublicIds)
            ->get(['id']);

        abort_if($tags->count() !== count($tagPublicIds), 404);

        return [$items->modelKeys(), $tags->modelKeys()];
    }
}
