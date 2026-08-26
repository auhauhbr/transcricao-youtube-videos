<?php

namespace App\Actions;

use App\Enums\UserDocumentRevisionKind;
use App\Exceptions\UserDocumentConflictException;
use App\Models\UserDocument;
use App\Models\UserTranscript;
use App\Support\UserDocumentSnapshot;
use Illuminate\Support\Facades\DB;

final readonly class RestoreUserDocumentRevision
{
    public function __construct(
        private CreateUserDocumentRevision $createRevision,
        private UserDocumentSnapshot $snapshot,
    ) {}

    /** @return array{document: UserDocument, restored: bool, backupCreated: bool} */
    public function handle(UserTranscript $userTranscript, string $revisionPublicId, int $expectedVersion): array
    {
        return DB::transaction(function () use ($userTranscript, $revisionPublicId, $expectedVersion): array {
            UserTranscript::query()->whereKey($userTranscript->getKey())->lockForUpdate()->firstOrFail();
            $document = UserDocument::query()
                ->where('user_transcript_id', $userTranscript->getKey())
                ->firstOrFail();

            if ($document->lock_version !== $expectedVersion) {
                throw new UserDocumentConflictException;
            }

            $revision = $document->revisions()
                ->where('public_id', $revisionPublicId)
                ->firstOrFail();

            if ($this->snapshot->matches($document->title, $document->content, $revision->title, $revision->content)) {
                return ['document' => $document, 'restored' => false, 'backupCreated' => false];
            }

            $backup = $this->createRevision->handle(
                $document,
                UserDocumentRevisionKind::RestoreBackup,
                $document->title,
                $document->content,
                $document->lock_version,
            );

            $updated = UserDocument::query()
                ->whereKey($document->getKey())
                ->where('lock_version', $expectedVersion)
                ->update([
                    'title' => $revision->title,
                    'content' => $revision->content,
                    'lock_version' => $expectedVersion + 1,
                    'updated_at' => now(),
                ]);

            if ($updated !== 1) {
                throw new UserDocumentConflictException;
            }

            return [
                'document' => UserDocument::query()->findOrFail($document->getKey()),
                'restored' => true,
                'backupCreated' => $backup['created'],
            ];
        });
    }
}
