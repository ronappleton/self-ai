<?php

namespace App\Support\Promotions;

use Carbon\CarbonImmutable;

class PromotionSignature
{
    public function __construct(
        public readonly string $verifierId,
        public readonly string $signature,
        public readonly string $nonce,
        public readonly CarbonImmutable $requestedAt,
        public readonly CarbonImmutable $expiresAt
    ) {
    }
}
