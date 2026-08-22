<?php

namespace App\Guest;

use App\Models\GuestUsage;

final readonly class GuestIdentity
{
    public function __construct(
        public string $tokenHash,
        public ?GuestUsage $usage,
        public ?string $tokenToSet,
    ) {}
}
