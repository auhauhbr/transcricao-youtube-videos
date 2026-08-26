<?php

namespace App\Actions;

use App\Exceptions\UserDocumentConflictException;
use App\Models\UserDocument;
use App\Models\UserTranscript;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class SaveUserDocument
{
    /**
     * @param  array<string, mixed>  $content
     * @return array{document: UserDocument, created: bool}
     */
    public function handle(UserTranscript $userTranscript, string $title, array $content, ?int $expectedVersion): array
    {
        try {
            return DB::transaction(function () use ($userTranscript, $title, $content, $expectedVersion): array {
                UserTranscript::query()->whereKey($userTranscript->getKey())->lockForUpdate()->firstOrFail();
                $document = UserDocument::query()
                    ->where('user_transcript_id', $userTranscript->getKey())
                    ->first();

                if ($document === null) {
                    if ($expectedVersion !== null) {
                        throw new UserDocumentConflictException;
                    }

                    return [
                        'document' => UserDocument::query()->create([
                            'user_transcript_id' => $userTranscript->getKey(),
                            'title' => $title,
                            'content' => $content,
                            'lock_version' => 1,
                        ]),
                        'created' => true,
                    ];
                }

                if ($expectedVersion === null || $document->lock_version !== $expectedVersion) {
                    throw new UserDocumentConflictException;
                }

                $updated = UserDocument::query()
                    ->whereKey($document->getKey())
                    ->where('lock_version', $expectedVersion)
                    ->update([
                        'title' => $title,
                        'content' => $content,
                        'lock_version' => $expectedVersion + 1,
                        'updated_at' => now(),
                    ]);

                if ($updated !== 1) {
                    throw new UserDocumentConflictException;
                }

                return [
                    'document' => UserDocument::query()->findOrFail($document->getKey()),
                    'created' => false,
                ];
            });
        } catch (QueryException $exception) {
            if (in_array($exception->errorInfo[0] ?? null, ['23000', '23505'], true)) {
                throw new UserDocumentConflictException(previous: $exception);
            }

            throw $exception;
        }
    }
}
