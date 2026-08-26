<?php

namespace App\Http\Controllers;

use App\Http\Requests\LibraryIndexRequest;
use App\Library\UserLibraryQuery;
use App\Models\Folder;
use App\Models\Tag;
use App\Models\UserTranscript;
use Inertia\Inertia;
use Inertia\Response;

class LibraryController extends Controller
{
    public function __invoke(LibraryIndexRequest $request, UserLibraryQuery $libraryQuery): Response
    {
        $user = $request->user();
        $filters = $request->filters();
        $items = $libraryQuery->paginate($user, $filters);
        $folders = Folder::query()
            ->where('user_id', $user->getKey())
            ->withCount('userTranscripts')
            ->orderBy('name')
            ->get(['id', 'public_id', 'name']);
        $tags = Tag::query()
            ->where('user_id', $user->getKey())
            ->orderBy('name')
            ->get(['public_id', 'name']);
        $allCount = UserTranscript::query()->where('user_id', $user->getKey())->count();
        $unfiledCount = UserTranscript::query()->where('user_id', $user->getKey())->whereNull('folder_id')->count();

        return Inertia::render('Library/Index', [
            'library' => [
                'items' => $items->getCollection()->map(fn (UserTranscript $item): array => $this->itemData($item))->all(),
                'pagination' => [
                    'currentPage' => $items->currentPage(),
                    'lastPage' => $items->lastPage(),
                    'perPage' => $items->perPage(),
                    'total' => $items->total(),
                    'previousPageUrl' => $items->previousPageUrl(),
                    'nextPageUrl' => $items->nextPageUrl(),
                ],
                'folders' => $folders->map(fn (Folder $folder): array => [
                    'publicId' => $folder->public_id,
                    'name' => $folder->name,
                    'count' => $folder->user_transcripts_count,
                ])->all(),
                'tags' => $tags->map(fn (Tag $tag): array => [
                    'publicId' => $tag->public_id,
                    'name' => $tag->name,
                ])->all(),
                'counts' => ['all' => $allCount, 'unfiled' => $unfiledCount],
                'languages' => $libraryQuery->languageOptions($user),
                'filters' => $filters,
            ],
        ]);
    }

    /**
     * @return array{
     *   publicId: string,
     *   title: string,
     *   channelName: string|null,
     *   thumbnailUrl: string|null,
     *   durationSeconds: int,
     *   languageCode: string,
     *   languageLabel: string,
     *   source: string,
     *   sourceLabel: string,
     *   addedAt: string,
     *   showUrl: string,
     *   workspaceUrl: string,
     *   destroyUrl: string,
     *   hasDocument: bool,
     *   folder: array{publicId: string, name: string}|null,
     *   tags: array<int, array{publicId: string, name: string}>
     * }
     */
    private function itemData(UserTranscript $item): array
    {
        $transcript = $item->transcript;
        $video = $transcript->video;

        return [
            'publicId' => $item->public_id,
            'title' => $video->title ?? 'Transcrição do YouTube',
            'channelName' => $video->channel_name,
            'thumbnailUrl' => $video->thumbnail_url,
            'durationSeconds' => $video->duration_seconds ?? 0,
            'languageCode' => $transcript->language_code,
            'languageLabel' => $transcript->language_name ?: $transcript->language_code,
            'source' => $transcript->source->value,
            'sourceLabel' => $transcript->source->publicLabel(),
            'addedAt' => $item->created_at->toIso8601String(),
            'showUrl' => route('library.show', $item->public_id, absolute: false),
            'workspaceUrl' => route('library.workspace', $item->public_id, absolute: false),
            'destroyUrl' => route('library.destroy', $item->public_id, absolute: false),
            'hasDocument' => (bool) $item->document_exists,
            'folder' => $item->folder === null ? null : [
                'publicId' => $item->folder->public_id,
                'name' => $item->folder->name,
            ],
            'tags' => $item->tags->map(fn (Tag $tag): array => [
                'publicId' => $tag->public_id,
                'name' => $tag->name,
            ])->all(),
        ];
    }
}
