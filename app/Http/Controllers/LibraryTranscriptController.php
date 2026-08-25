<?php

namespace App\Http\Controllers;

use App\Actions\FindUserTranscript;
use App\Actions\RemoveLibraryItems;
use App\Transcript\TranscriptResultPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LibraryTranscriptController extends Controller
{
    public function show(
        Request $request,
        string $userTranscript,
        FindUserTranscript $findUserTranscript,
        TranscriptResultPresenter $presenter,
    ): Response {
        $item = $findUserTranscript->handle($request->user(), $userTranscript);
        $item->load(['transcript.video', 'transcript.segments', 'transcript.chapters']);

        return Inertia::render('Library/Show', [
            ...$presenter->present($item->transcript),
            'downloadUrl' => route('library.download', $item->public_id, absolute: false),
            'backUrl' => route('library.index', absolute: false),
        ]);
    }

    public function destroy(
        Request $request,
        string $userTranscript,
        FindUserTranscript $findUserTranscript,
        RemoveLibraryItems $removeItems,
    ): RedirectResponse {
        $item = $findUserTranscript->handle($request->user(), $userTranscript);
        $removeItems->handle($request->user(), [$item->public_id]);

        return to_route('library.index')->with('status', 'library-item-removed')->with('message', 'Transcrição removida da biblioteca.');
    }
}
