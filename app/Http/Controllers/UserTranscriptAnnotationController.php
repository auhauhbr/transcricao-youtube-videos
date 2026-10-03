<?php

namespace App\Http\Controllers;

use App\Actions\FindUserTranscript;
use App\Actions\FindUserTranscriptAnnotation;
use App\Enums\UserTranscriptAnnotationType;
use App\Http\Requests\StoreUserTranscriptAnnotationRequest;
use App\Http\Requests\UpdateUserTranscriptAnnotationRequest;
use App\Models\TranscriptSegment;
use App\Models\UserTranscript;
use App\Support\UserTranscriptAnnotationPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UserTranscriptAnnotationController extends Controller
{
    public function store(
        StoreUserTranscriptAnnotationRequest $request,
        string $userTranscript,
        FindUserTranscript $findUserTranscript,
        UserTranscriptAnnotationPresenter $annotationPresenter,
    ): JsonResponse {
        $item = $findUserTranscript->handle($request->user(), $userTranscript);
        $validated = $request->validated();
        $this->ensureTimestampBelongsToTranscript($item, $validated['start_ms']);

        $exists = $item->annotations()
            ->where('start_ms', $validated['start_ms'])
            ->where('type', $validated['type'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['type' => 'Esta annotation já existe neste timestamp.']);
        }

        $annotation = $item->annotations()->create([
            'start_ms' => $validated['start_ms'],
            'type' => $validated['type'],
            'text' => $validated['type'] === UserTranscriptAnnotationType::Bookmark->value ? null : $validated['text'],
        ]);

        return response()->json(['annotation' => $annotationPresenter->annotation($annotation, $item)], 201);
    }

    public function update(
        UpdateUserTranscriptAnnotationRequest $request,
        string $userTranscript,
        string $annotation,
        FindUserTranscript $findUserTranscript,
        FindUserTranscriptAnnotation $findAnnotation,
        UserTranscriptAnnotationPresenter $annotationPresenter,
    ): JsonResponse {
        $item = $findUserTranscript->handle($request->user(), $userTranscript);
        $ownedAnnotation = $findAnnotation->handle($item, $annotation);

        abort_unless($ownedAnnotation->type === UserTranscriptAnnotationType::Note, 404);
        $ownedAnnotation->update($request->validated());

        return response()->json(['annotation' => $annotationPresenter->annotation($ownedAnnotation->refresh(), $item)]);
    }

    public function destroy(
        Request $request,
        string $userTranscript,
        string $annotation,
        FindUserTranscript $findUserTranscript,
        FindUserTranscriptAnnotation $findAnnotation,
    ): JsonResponse {
        $item = $findUserTranscript->handle($request->user(), $userTranscript);
        $findAnnotation->handle($item, $annotation)->delete();

        return response()->json(status: 204);
    }

    private function ensureTimestampBelongsToTranscript(UserTranscript $item, int $startMs): void
    {
        $exists = TranscriptSegment::query()
            ->where('transcript_id', $item->transcript_id)
            ->where('start_ms', $startMs)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages(['start_ms' => 'O timestamp deve corresponder a um bloco da transcrição.']);
        }
    }
}
