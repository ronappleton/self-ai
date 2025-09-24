<?php

namespace App\Support\Legacy;

use Carbon\CarbonImmutable;
use RuntimeException;

class LegacyPreviewRateLimitException extends RuntimeException
{
    public function __construct(
        private readonly int $retryAfterSeconds,
        private readonly CarbonImmutable $cooldownEndsAt
    ) {
        parent::__construct('Legacy preview session is in a cooldown period.');
    }

    public function retryAfterSeconds(): int
    {
        return max(0, $this->retryAfterSeconds);
    }

    public function cooldownEndsAt(): CarbonImmutable
    {
        return $this->cooldownEndsAt;
    }
}
