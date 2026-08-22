<?php

namespace App\Actions;

use App\Enums\ExtractionErrorCode;
use App\Enums\ExtractionStatus;
use App\Guest\GuestExtractionQuota;
use App\Models\Extraction;
use Illuminate\Support\Facades\DB;

final readonly class FailTranscriptExtraction
{
    public function __construct(private GuestExtractionQuota $guestQuota) {}

    public function handle(Extraction $extraction, ExtractionErrorCode $code, string $message): Extraction
    {
        return DB::transaction(function () use ($extraction, $code, $message): Extraction {
            $lockedExtraction = Extraction::query()
                ->lockForUpdate()
                ->findOrFail($extraction->getKey());

            if ($lockedExtraction->status === ExtractionStatus::Ready) {
                return $lockedExtraction;
            }

            if ($lockedExtraction->status === ExtractionStatus::Pending) {
                $lockedExtraction->markProcessing();
            }

            if ($lockedExtraction->status === ExtractionStatus::Processing) {
                $lockedExtraction->markFailed($code, $message);
            }

            $this->guestQuota->releaseFailedExtraction($lockedExtraction);

            return $lockedExtraction->refresh();
        }, 3);
    }
}
