<?php

namespace App\Actions;

use App\Enums\VideoProvider;
use App\Jobs\ExtractTranscriptJob;
use App\Models\Extraction;
use App\Models\User;
use App\Models\Video;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class RequestTranscriptExtraction
{
    public function handle(
        VideoProvider $provider,
        string $providerVideoId,
        ?string $requestedLanguage = null,
        ?User $user = null,
    ): Extraction {
        $this->validateIdentity($provider, $providerVideoId);
        $requestedLanguage = $this->normalizeLanguage($requestedLanguage);

        return DB::transaction(function () use ($provider, $providerVideoId, $requestedLanguage, $user): Extraction {
            $video = Video::query()->firstOrCreate([
                'provider' => $provider,
                'provider_video_id' => $providerVideoId,
            ]);

            $extraction = new Extraction([
                'requested_language' => $requestedLanguage,
            ]);
            $extraction->video()->associate($video);
            $extraction->user()->associate($user);
            $extraction->save();

            ExtractTranscriptJob::dispatch($extraction->getKey())->afterCommit();

            return $extraction;
        });
    }

    private function validateIdentity(VideoProvider $provider, string $providerVideoId): void
    {
        match ($provider) {
            VideoProvider::YouTube => preg_match('/\A[A-Za-z0-9_-]{11}\z/', $providerVideoId) === 1
                ?: throw new InvalidArgumentException('The YouTube video ID must contain exactly 11 valid characters.'),
        };
    }

    private function normalizeLanguage(?string $requestedLanguage): ?string
    {
        if ($requestedLanguage === null) {
            return null;
        }

        $requestedLanguage = trim($requestedLanguage);

        if ($requestedLanguage === '' || mb_strlen($requestedLanguage) > 35) {
            throw new InvalidArgumentException('The requested language is invalid.');
        }

        return $requestedLanguage;
    }
}
