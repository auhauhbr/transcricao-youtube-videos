<?php

namespace App\Http\Controllers;

use App\Actions\MoveLibraryItems;
use App\Actions\RemoveLibraryItems;
use App\Actions\TagLibraryItems;
use App\Http\Requests\BulkMoveLibraryItemsRequest;
use App\Http\Requests\BulkRemoveLibraryItemsRequest;
use App\Http\Requests\BulkTagLibraryItemsRequest;
use Illuminate\Http\RedirectResponse;

class LibraryBulkController extends Controller
{
    public function move(BulkMoveLibraryItemsRequest $request, MoveLibraryItems $moveItems): RedirectResponse
    {
        $data = $request->validated();
        $count = $moveItems->handle($request->user(), $data['item_public_ids'], $data['folder_public_id'] ?? null);

        return back()->with('status', 'library-items-moved')->with('message', $this->message($count, 'movida', 'movidas'));
    }

    public function addTags(BulkTagLibraryItemsRequest $request, TagLibraryItems $tagItems): RedirectResponse
    {
        $data = $request->validated();
        $tagItems->add($request->user(), $data['item_public_ids'], $data['tag_public_ids']);

        return back()->with('status', 'library-tags-updated')->with('message', 'Tags adicionadas.');
    }

    public function removeTags(BulkTagLibraryItemsRequest $request, TagLibraryItems $tagItems): RedirectResponse
    {
        $data = $request->validated();
        $tagItems->remove($request->user(), $data['item_public_ids'], $data['tag_public_ids']);

        return back()->with('status', 'library-tags-updated')->with('message', 'Tags removidas.');
    }

    public function destroy(BulkRemoveLibraryItemsRequest $request, RemoveLibraryItems $removeItems): RedirectResponse
    {
        $count = $removeItems->handle($request->user(), $request->validated('item_public_ids'));

        return back()->with('status', 'library-items-removed')->with('message', $this->message($count, 'removida', 'removidas'));
    }

    private function message(int $count, string $singular, string $plural): string
    {
        return sprintf('%d %s %s.', $count, $count === 1 ? 'transcrição' : 'transcrições', $count === 1 ? $singular : $plural);
    }
}
