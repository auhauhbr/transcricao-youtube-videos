<?php

namespace App\Actions;

use App\Enums\UserDocumentRevisionKind;
use App\Models\UserDocument;
use App\Models\UserDocumentRevision;
use App\Models\UserTranscript;
use App\Support\UserDocumentSnapshot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class CreateUserDocumentRevision
{
    public const AUTOMATIC_INTERVAL_MINUTES = 10;

    public const MAX_AUTOMATIC_REVISIONS = 100;

    public function __construct(private UserDocumentSnapshot $snapshot) {}

    /**
     * @param  array<string, mixed>  $content
     * @return array{revision: UserDocumentRevision, created: bool}
     */
    public function handle(
        UserDocument $document,
        UserDocumentRevisionKind $kind,
        string $title,
        array $content,
        int $documentLockVersion,
        bool $deduplicate = false,
    ): array {
        return DB::transaction(function () use ($document, $kind, $title, $content, $documentLockVersion, $deduplicate): array {
            // This parent lock is the serialization point shared by save, manual revision and restore.
            UserTranscript::query()->whereKey($document->user_transcript_id)->lockForUpdate()->firstOrFail();

            if ($kind === UserDocumentRevisionKind::Baseline && $document->revisions()->where('kind', $kind->value)->exists()) {
                throw new LogicException('A baseline revision already exists.');
            }

            $latest = $document->revisions()
                ->orderByDesc('revision_number')
                ->first();

            if (
                $deduplicate
                && $latest !== null
                && $this->snapshot->matches($latest->title, $latest->content, $title, $content)
            ) {
                return ['revision' => $latest, 'created' => false];
            }

            $revision = $document->revisions()->create([
                'revision_number' => ((int) $document->revisions()->max('revision_number')) + 1,
                'kind' => $kind,
                'title' => $title,
                'content' => $content,
                'document_lock_version' => $documentLockVersion,
            ]);

            if ($kind === UserDocumentRevisionKind::Automatic) {
                $this->pruneAutomaticRevisions($document);
            }

            return ['revision' => $revision, 'created' => true];
        });
    }

    public function automaticCheckpointIsDue(UserDocument $document): bool
    {
        $latestCreatedAt = $document->revisions()->max('created_at');
        $reference = is_string($latestCreatedAt)
            ? Carbon::parse($latestCreatedAt)
            : $document->created_at;

        return $reference->lte(now()->subMinutes(self::AUTOMATIC_INTERVAL_MINUTES));
    }

    private function pruneAutomaticRevisions(UserDocument $document): void
    {
        $excess = $document->revisions()
            ->where('kind', UserDocumentRevisionKind::Automatic->value)
            ->count() - self::MAX_AUTOMATIC_REVISIONS;

        if ($excess <= 0) {
            return;
        }

        $expiredIds = $document->revisions()
            ->where('kind', UserDocumentRevisionKind::Automatic->value)
            ->orderBy('created_at')
            ->orderBy('revision_number')
            ->limit($excess)
            ->pluck('id');

        UserDocumentRevision::query()->whereIn('id', $expiredIds)->delete();
    }
}
