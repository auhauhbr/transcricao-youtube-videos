<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFolderRequest;
use App\Http\Requests\UpdateFolderRequest;
use App\Models\Folder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LibraryFolderController extends Controller
{
    public function store(StoreFolderRequest $request): RedirectResponse
    {
        $request->user()->folders()->create($request->validated());

        return back()->with('status', 'folder-created')->with('message', 'Pasta criada.');
    }

    public function update(UpdateFolderRequest $request, string $folder): RedirectResponse
    {
        $ownedFolder = $this->findOwned($request, $folder);
        $ownedFolder->update($request->validated());

        return back()->with('status', 'folder-renamed')->with('message', 'Pasta renomeada.');
    }

    public function destroy(Request $request, string $folder): RedirectResponse
    {
        $ownedFolder = $this->findOwned($request, $folder);

        DB::transaction(function () use ($ownedFolder): void {
            $ownedFolder->userTranscripts()->update(['folder_id' => null]);
            $ownedFolder->delete();
        });

        return to_route('library.index')->with('status', 'folder-deleted')->with('message', 'Pasta excluída. As transcrições foram movidas para Sem pasta.');
    }

    private function findOwned(Request $request, string $publicId): Folder
    {
        return Folder::query()
            ->where('user_id', $request->user()->getKey())
            ->where('public_id', $publicId)
            ->firstOrFail();
    }
}
