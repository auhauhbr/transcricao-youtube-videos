<?php

namespace App\Support;

use App\Models\UserDocument;
use App\Models\UserDocumentRevision;

final readonly class UserDocumentPresenter
{
    public function __construct(private UserDocumentSnapshot $snapshot) {}

    /** @return array{publicId: string, title: string, content: array<string, mixed>, lockVersion: int, updatedAt: string} */
    public function document(UserDocument $document): array
    {
        return [
            'publicId' => $document->public_id,
            'title' => $document->title,
            'content' => $document->content,
            'lockVersion' => $document->lock_version,
            'updatedAt' => $document->updated_at->toIso8601String(),
        ];
    }

    /** @return array{publicId: string, revisionNumber: int, kind: string, title: string, documentLockVersion: int, createdAt: string, preview: string, isBaseline: bool, urls: array{show: string, restore: string}} */
    public function revisionMetadata(UserDocumentRevision $revision, string $userTranscriptPublicId): array
    {
        return [
            'publicId' => $revision->public_id,
            'revisionNumber' => $revision->revision_number,
            'kind' => $revision->kind->value,
            'title' => $revision->title,
            'documentLockVersion' => $revision->document_lock_version,
            'createdAt' => $revision->created_at->toIso8601String(),
            'preview' => $this->snapshot->preview($revision->content),
            'isBaseline' => $revision->kind->value === 'baseline',
            'urls' => [
                'show' => route('library.document.revisions.show', [$userTranscriptPublicId, $revision->public_id], absolute: false),
                'restore' => route('library.document.revisions.restore', [$userTranscriptPublicId, $revision->public_id], absolute: false),
            ],
        ];
    }

    /** @return array{publicId: string, revisionNumber: int, kind: string, title: string, content: array<string, mixed>, documentLockVersion: int, createdAt: string} */
    public function revision(UserDocumentRevision $revision): array
    {
        return [
            'publicId' => $revision->public_id,
            'revisionNumber' => $revision->revision_number,
            'kind' => $revision->kind->value,
            'title' => $revision->title,
            'content' => $revision->content,
            'documentLockVersion' => $revision->document_lock_version,
            'createdAt' => $revision->created_at->toIso8601String(),
        ];
    }
}
