<?php

namespace App\Actions;

use App\Models\UserTranscript;
use Illuminate\Support\Str;

final class EnsureUserTranscript
{
    public function handle(int $userId, int $transcriptId): UserTranscript
    {
        return UserTranscript::query()->createOrFirst([
            'user_id' => $userId,
            'transcript_id' => $transcriptId,
        ]);
    }

    /** @param iterable<int> $transcriptIds */
    public function handleMany(int $userId, iterable $transcriptIds): int
    {
        $transcriptIds = collect($transcriptIds)
            ->map(static fn (mixed $id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($transcriptIds->isEmpty()) {
            return 0;
        }

        $timestamp = now();
        $rows = $transcriptIds->map(static fn (int $transcriptId): array => [
            'public_id' => (string) Str::ulid(),
            'user_id' => $userId,
            'transcript_id' => $transcriptId,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ])->all();

        return UserTranscript::query()->insertOrIgnore($rows);
    }
}
