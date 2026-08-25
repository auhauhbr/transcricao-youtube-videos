<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTagRequest;
use App\Http\Requests\UpdateTagRequest;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LibraryTagController extends Controller
{
    public function store(StoreTagRequest $request): RedirectResponse
    {
        $request->user()->tags()->create($request->validated());

        return back()->with('status', 'tag-created')->with('message', 'Tag criada.');
    }

    public function update(UpdateTagRequest $request, string $tag): RedirectResponse
    {
        $ownedTag = $this->findOwned($request, $tag);
        $ownedTag->update($request->validated());

        return back()->with('status', 'tag-renamed')->with('message', 'Tag renomeada.');
    }

    public function destroy(Request $request, string $tag): RedirectResponse
    {
        $this->findOwned($request, $tag)->delete();

        return to_route('library.index')->with('status', 'tag-deleted')->with('message', 'Tag excluída. Nenhuma transcrição foi apagada.');
    }

    private function findOwned(Request $request, string $publicId): Tag
    {
        return Tag::query()
            ->where('user_id', $request->user()->getKey())
            ->where('public_id', $publicId)
            ->firstOrFail();
    }
}
