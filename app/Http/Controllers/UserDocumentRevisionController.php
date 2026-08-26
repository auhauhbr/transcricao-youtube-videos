<?php

namespace App\Http\Controllers;

use App\Actions\CreateManualUserDocumentRevision;
use App\Actions\FindUserTranscript;
use App\Actions\RestoreUserDocumentRevision;
use App\Exceptions\UserDocumentConflictException;
use App\Http\Requests\CreateManualUserDocumentRevisionRequest;
use App\Http\Requests\RestoreUserDocumentRevisionRequest;
use App\Models\UserDocumentRevision;
use App\Support\UserDocumentPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserDocumentRevisionController extends Controller
{
    public function index(
        Request $request,
        string $userTranscript,
        FindUserTranscript $findUserTranscript,
        UserDocumentPresenter $presenter,
    ): JsonResponse {
        $request->validate(['page' => ['sometimes', 'integer', 'min:1']]);
        $item = $findUserTranscript->handle($request->user(), $userTranscript);
        $document = $item->document()->first();

        if ($document === null) {
            return response()->json([
                'data' => [],
                'meta' => ['currentPage' => 1, 'lastPage' => 1, 'perPage' => 20, 'total' => 0],
            ]);
        }

        $revisions = $document->revisions()
            ->orderByDesc('revision_number')
            ->paginate(20);

        return response()->json([
            'data' => $revisions->getCollection()
                ->map(fn (UserDocumentRevision $revision): array => $presenter->revisionMetadata($revision, $item->public_id))
                ->values(),
            'meta' => [
                'currentPage' => $revisions->currentPage(),
                'lastPage' => $revisions->lastPage(),
                'perPage' => $revisions->perPage(),
                'total' => $revisions->total(),
            ],
        ]);
    }

    public function show(
        Request $request,
        string $userTranscript,
        string $revision,
        FindUserTranscript $findUserTranscript,
        UserDocumentPresenter $presenter,
    ): JsonResponse {
        $item = $findUserTranscript->handle($request->user(), $userTranscript);
        $document = $item->document()->firstOrFail();
        $snapshot = $document->revisions()->where('public_id', $revision)->firstOrFail();

        return response()->json(['revision' => $presenter->revision($snapshot)]);
    }

    public function store(
        CreateManualUserDocumentRevisionRequest $request,
        string $userTranscript,
        FindUserTranscript $findUserTranscript,
        CreateManualUserDocumentRevision $createManualRevision,
        UserDocumentPresenter $presenter,
    ): JsonResponse {
        $item = $findUserTranscript->handle($request->user(), $userTranscript);

        try {
            $result = $createManualRevision->handle($item, $request->integer('expected_lock_version'));
        } catch (UserDocumentConflictException) {
            return $this->conflict();
        }

        return response()->json([
            'created' => $result['created'],
            'code' => $result['created'] ? 'revision_created' : 'already_current',
            'revision' => $presenter->revisionMetadata($result['revision'], $item->public_id),
        ], $result['created'] ? 201 : 200);
    }

    public function restore(
        RestoreUserDocumentRevisionRequest $request,
        string $userTranscript,
        string $revision,
        FindUserTranscript $findUserTranscript,
        RestoreUserDocumentRevision $restoreRevision,
        UserDocumentPresenter $presenter,
    ): JsonResponse {
        $item = $findUserTranscript->handle($request->user(), $userTranscript);

        try {
            $result = $restoreRevision->handle($item, $revision, $request->integer('expected_lock_version'));
        } catch (UserDocumentConflictException) {
            return $this->conflict();
        }

        return response()->json([
            'restored' => $result['restored'],
            'backupCreated' => $result['backupCreated'],
            'document' => $presenter->document($result['document']),
        ]);
    }

    private function conflict(): JsonResponse
    {
        return response()->json([
            'code' => 'document_conflict',
            'message' => 'Este documento foi alterado em outra aba.',
        ], 409);
    }
}
