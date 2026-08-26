<?php

namespace App\Actions;

use App\Enums\UserDocumentRevisionKind;
use App\Exceptions\UserDocumentConflictException;
use App\Models\UserDocument;
use App\Models\UserTranscript;
use App\Support\UserDocumentSnapshot;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final readonly class SaveUserDocument
{
    public function __construct(
        private BuildUserDocumentSeed $seedBuilder,
        private CreateUserDocumentRevision $createRevision,
        private UserDocumentSnapshot $snapshot,
    ) {}

    /**
     * @param  array<string, mixed>  $content
     * @return array{document: UserDocument, created: bool, automaticRevisionCreated: bool}
     */
    public function handle(UserTranscript $userTranscript, string $title, array $content, ?int $expectedVersion): array
    {
        $baseline = $expectedVersion === null
            ? $this->seedBuilder->handle($userTranscript->transcript)
            : null;

        try {
            return DB::transaction(function () use ($userTranscript, $title, $content, $expectedVersion, $baseline): array {
                UserTranscript::query()->whereKey($userTranscript->getKey())->lockForUpdate()->firstOrFail();
                $document = UserDocument::query()
                    ->where('user_transcript_id', $userTranscript->getKey())
                    ->first();

                if ($document === null) {
                    if ($expectedVersion !== null) {
                        throw new UserDocumentConflictException;
                    }

                    $document = UserDocument::query()->create([
                        'user_transcript_id' => $userTranscript->getKey(),
                        'title' => $title,
                        'content' => $content,
                        'lock_version' => 1,
                    ]);

                    /** @var array{title: string, content: array<string, mixed>} $baseline */
                    $this->createRevision->handle(
                        $document,
                        UserDocumentRevisionKind::Baseline,
                        $baseline['title'],
                        $baseline['content'],
                        0,
                    );

                    return [
                        'document' => $document,
                        'created' => true,
                        'automaticRevisionCreated' => false,
                    ];
                }

                if ($expectedVersion === null || $document->lock_version !== $expectedVersion) {
                    throw new UserDocumentConflictException;
                }

                if ($this->snapshot->matches($document->title, $document->content, $title, $content)) {
                    return [
                        'document' => $document,
                        'created' => false,
                        'automaticRevisionCreated' => false,
                    ];
                }

                $automaticRevisionCreated = false;

                if ($this->createRevision->automaticCheckpointIsDue($document)) {
                    $revision = $this->createRevision->handle(
                        $document,
                        UserDocumentRevisionKind::Automatic,
                        $document->title,
                        $document->content,
                        $document->lock_version,
                        deduplicate: true,
                    );
                    $automaticRevisionCreated = $revision['created'];
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
                    'automaticRevisionCreated' => $automaticRevisionCreated,
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
