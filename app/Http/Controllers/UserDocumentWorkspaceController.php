<?php

namespace App\Http\Controllers;

use App\Actions\BuildUserDocumentSeed;
use App\Actions\FindUserTranscript;
use App\Actions\SaveUserDocument;
use App\Exceptions\UserDocumentConflictException;
use App\Http\Requests\SaveUserDocumentRequest;
use App\Models\UserDocument;
use App\Transcript\TranscriptResultPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserDocumentWorkspaceController extends Controller
{
    public function show(
        Request $request,
        string $userTranscript,
        FindUserTranscript $findUserTranscript,
        TranscriptResultPresenter $transcriptPresenter,
        BuildUserDocumentSeed $seedBuilder,
    ): Response {
        $item = $findUserTranscript->handle($request->user(), $userTranscript);
        $item->load(['document', 'transcript.video', 'transcript.segments', 'transcript.chapters']);
        $source = $transcriptPresenter->present($item->transcript);

        return Inertia::render('Workspace/Show', [
            'workspace' => [
                'userTranscriptPublicId' => $item->public_id,
                'document' => $item->document === null ? null : $this->documentData($item->document),
                'seed' => $item->document === null ? $seedBuilder->handle($item->transcript) : null,
                'source' => $source,
                'urls' => [
                    'save' => route('library.document.update', $item->public_id, absolute: false),
                    'library' => route('library.index', absolute: false),
                    'show' => route('library.show', $item->public_id, absolute: false),
                ],
            ],
        ]);
    }

    public function update(
        SaveUserDocumentRequest $request,
        string $userTranscript,
        FindUserTranscript $findUserTranscript,
        SaveUserDocument $saveDocument,
    ): JsonResponse {
        $item = $findUserTranscript->handle($request->user(), $userTranscript);
        $validated = $request->validated();

        try {
            $result = $saveDocument->handle(
                $item,
                $validated['title'],
                $validated['content'],
                $validated['lock_version'],
            );
        } catch (UserDocumentConflictException) {
            return response()->json([
                'code' => 'document_conflict',
                'message' => 'Este documento foi alterado em outra aba.',
            ], 409);
        }

        return response()->json(
            ['document' => $this->documentData($result['document'])],
            $result['created'] ? 201 : 200,
        );
    }

    /** @return array{publicId: string, title: string, content: array<string, mixed>, lockVersion: int, updatedAt: string} */
    private function documentData(UserDocument $document): array
    {
        return [
            'publicId' => $document->public_id,
            'title' => $document->title,
            'content' => $document->content,
            'lockVersion' => $document->lock_version,
            'updatedAt' => $document->updated_at->toIso8601String(),
        ];
    }
}
