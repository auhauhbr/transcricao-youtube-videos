<?php

namespace App\Actions;

use App\Enums\UserDocumentRevisionKind;
use App\Exceptions\UserDocumentConflictException;
use App\Models\UserDocument;
use App\Models\UserDocumentRevision;
use App\Models\UserTranscript;
use Illuminate\Support\Facades\DB;

final readonly class CreateManualUserDocumentRevision
{
    public function __construct(private CreateUserDocumentRevision $createRevision) {}

    /** @return array{revision: UserDocumentRevision, created: bool} */
    public function handle(UserTranscript $userTranscript, int $expectedVersion): array
    {
        return DB::transaction(function () use ($userTranscript, $expectedVersion): array {
            UserTranscript::query()->whereKey($userTranscript->getKey())->lockForUpdate()->firstOrFail();
            $document = UserDocument::query()
                ->where('user_transcript_id', $userTranscript->getKey())
                ->firstOrFail();

            if ($document->lock_version !== $expectedVersion) {
                throw new UserDocumentConflictException;
            }

            return $this->createRevision->handle(
                $document,
                UserDocumentRevisionKind::Manual,
                $document->title,
                $document->content,
                $document->lock_version,
                deduplicate: true,
            );
        });
    }
}
