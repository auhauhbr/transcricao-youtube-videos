<?php

namespace App\Actions;

use App\Enums\VideoProvider;
use App\Guest\GuestExtractionQuota;
use App\Jobs\ExtractTranscriptJob;
use App\Models\Extraction;
use App\Models\GuestUsage;
use App\Models\User;
use App\Models\Video;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class RequestTranscriptExtraction
{
    public function __construct(private readonly GuestExtractionQuota $guestQuota) {}

    public function handle(
        VideoProvider $provider,
        string $providerVideoId,
        ?string $requestedLanguage = null,
        ?User $user = null,
        ?string $guestTokenHash = null,
    ): Extraction {
        $this->validateIdentity($provider, $providerVideoId);
        $requestedLanguage = $this->normalizeLanguage($requestedLanguage);

        if (($user === null) === ($guestTokenHash === null)) {
            throw new InvalidArgumentException('Exactly one extraction owner context must be provided.');
        }

        if ($user !== null) {
            return DB::transaction(fn (): Extraction => $this->createExtraction(
                $provider,
                $providerVideoId,
                $requestedLanguage,
                $user,
                null,
            ));
        }

        return $this->guestQuota->reserve(
            $guestTokenHash,
            fn (GuestUsage $lockedUsage): Extraction => $this->createExtraction(
                $provider,
                $providerVideoId,
                $requestedLanguage,
                null,
                $lockedUsage,
            ),
        );
    }

    private function createExtraction(
        VideoProvider $provider,
        string $providerVideoId,
        ?string $requestedLanguage,
        ?User $user,
        ?GuestUsage $guestUsage,
    ): Extraction {
        $video = Video::query()->firstOrCreate([
            'provider' => $provider,
            'provider_video_id' => $providerVideoId,
        ]);

        $extraction = new Extraction([
            'requested_language' => $requestedLanguage,
        ]);
        $extraction->video()->associate($video);
        $extraction->user()->associate($user);
        $extraction->guestUsage()->associate($guestUsage);
        $extraction->save();

        ExtractTranscriptJob::dispatch($extraction->getKey())->afterCommit();

        return $extraction;
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
