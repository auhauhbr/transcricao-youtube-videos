<?php

namespace App\Guest;

use App\Models\GuestUsage;

final readonly class GuestIdentity
{
    public function __construct(
        public GuestUsage $usage,
        public ?string $tokenToSet,
    ) {}
}
