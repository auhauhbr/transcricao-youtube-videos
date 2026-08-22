<?php

namespace App\Http\Controllers;

use App\Models\UserTranscript;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LibraryController extends Controller
{
    private const ITEMS_PER_PAGE = 20;

    public function __invoke(Request $request): Response
    {
        $items = UserTranscript::query()
            ->where('user_id', $request->user()->getKey())
            ->with([
                'transcript:id,video_id,language_code,language_name,source',
                'transcript.video:id,title,channel_name,thumbnail_url,duration_seconds',
            ])
            ->latest('created_at')
            ->latest('id')
            ->paginate(self::ITEMS_PER_PAGE)
            ->withQueryString();

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
     *   languageName: string|null,
     *   sourceLabel: string,
     *   addedAt: string,
     *   showUrl: string,
     *   destroyUrl: string
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
            'languageName' => $transcript->language_name,
            'sourceLabel' => $transcript->source->publicLabel(),
            'addedAt' => $item->created_at->toIso8601String(),
            'showUrl' => route('library.show', $item->public_id, absolute: false),
            'destroyUrl' => route('library.destroy', $item->public_id, absolute: false),
        ];
    }
}
