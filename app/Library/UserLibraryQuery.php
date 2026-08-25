<?php

namespace App\Library;

use App\Models\Folder;
use App\Models\Tag;
use App\Models\User;
use App\Models\UserTranscript;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class UserLibraryQuery
{
    private const ITEMS_PER_PAGE = 20;

    /**
     * @param  array{q: string, folder: string|null, tag: string|null, language: string|null, source: string|null, sort: string}  $filters
     * @return LengthAwarePaginator<int, UserTranscript>
     */
    public function paginate(User $user, array $filters): LengthAwarePaginator
    {
        $query = UserTranscript::query()
            ->where('user_transcripts.user_id', $user->getKey())
            ->with([
                'folder:id,public_id,name',
                'tags:id,public_id,name',
                'transcript:id,video_id,language_code,language_name,source',
                'transcript.video:id,provider,title,channel_name,thumbnail_url,duration_seconds',
            ]);

        $this->applySearch($query, $filters['q']);
        $this->applyFolder($query, $user, $filters['folder']);
        $this->applyTag($query, $user, $filters['tag']);

        if ($filters['language'] !== null) {
            $query->whereHas('transcript', fn (Builder $transcripts) => $transcripts->where('language_code', $filters['language']));
        }

        if ($filters['source'] !== null) {
            $query->whereHas('transcript', fn (Builder $transcripts) => $transcripts->where('source', $filters['source']));
        }

        $this->applySort($query, $filters['sort']);

        return $query->paginate(self::ITEMS_PER_PAGE)->withQueryString();
    }

    /** @return array<int, array{code: string, label: string}> */
    public function languageOptions(User $user): array
    {
        return DB::table('user_transcripts')
            ->join('transcripts', 'transcripts.id', '=', 'user_transcripts.transcript_id')
            ->where('user_transcripts.user_id', $user->getKey())
            ->whereNotNull('transcripts.language_code')
            ->select('transcripts.language_code', 'transcripts.language_name')
            ->distinct()
            ->orderBy('transcripts.language_name')
            ->orderBy('transcripts.language_code')
            ->get()
            ->map(fn (object $language): array => [
                'code' => $language->language_code,
                'label' => $language->language_name ?: $language->language_code,
            ])->all();
    }

    /** @param Builder<UserTranscript> $query */
    private function applySearch(Builder $query, string $search): void
    {
        if ($search === '') {
            return;
        }

        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
        $operator = DB::getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';

        $query->where(function (Builder $items) use ($operator, $pattern): void {
            $items->whereHas('transcript.video', function (Builder $videos) use ($operator, $pattern): void {
                $videos->whereRaw("title {$operator} ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("channel_name {$operator} ? ESCAPE '!'", [$pattern]);
            })->orWhereHas('tags', fn (Builder $tags) => $tags->whereRaw("name {$operator} ? ESCAPE '!'", [$pattern]));
        });
    }

    /** @param Builder<UserTranscript> $query */
    private function applyFolder(Builder $query, User $user, ?string $folderPublicId): void
    {
        if ($folderPublicId === null) {
            return;
        }

        if ($folderPublicId === 'none') {
            $query->whereNull('folder_id');

            return;
        }

        $folder = Folder::query()
            ->where('user_id', $user->getKey())
            ->where('public_id', $folderPublicId)
            ->firstOrFail();
        $query->where('folder_id', $folder->getKey());
    }

    /** @param Builder<UserTranscript> $query */
    private function applyTag(Builder $query, User $user, ?string $tagPublicId): void
    {
        if ($tagPublicId === null) {
            return;
        }

        $tag = Tag::query()
            ->where('user_id', $user->getKey())
            ->where('public_id', $tagPublicId)
            ->firstOrFail();
        $query->whereHas('tags', fn (Builder $tags) => $tags->whereKey($tag->getKey()));
    }

    /** @param Builder<UserTranscript> $query */
    private function applySort(Builder $query, string $sort): void
    {
        if (str_starts_with($sort, 'title_')) {
            $direction = $sort === 'title_asc' ? 'asc' : 'desc';
            $query->join('transcripts as sorted_transcripts', 'sorted_transcripts.id', '=', 'user_transcripts.transcript_id')
                ->join('videos as sorted_videos', 'sorted_videos.id', '=', 'sorted_transcripts.video_id')
                ->select('user_transcripts.*')
                ->orderByRaw("LOWER(sorted_videos.title) {$direction}")
                ->orderBy('user_transcripts.id', $direction);

            return;
        }

        $direction = $sort === 'oldest' ? 'asc' : 'desc';
        $query->orderBy('user_transcripts.created_at', $direction)
            ->orderBy('user_transcripts.id', $direction);
    }
}
