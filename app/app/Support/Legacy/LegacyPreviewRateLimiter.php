<?php

namespace App\Support\Legacy;

use App\Models\LegacyPreviewSession;
use Carbon\CarbonImmutable;

class LegacyPreviewRateLimiter
{
    private int $maxMessages;

    private int $windowSeconds;

    private int $cooldownSeconds;

    /**
     * @param  array{max_messages?: int, window_seconds?: int, cooldown_seconds?: int}|null  $config
     */
    public function __construct(?array $config = null)
    {
        $config ??= config('legacy.rate_limit', []);
        $this->maxMessages = max(1, (int) ($config['max_messages'] ?? 3));
        $this->windowSeconds = max(1, (int) ($config['window_seconds'] ?? 900));
        $this->cooldownSeconds = max(1, (int) ($config['cooldown_seconds'] ?? 600));
    }

    /**
     * @throws LegacyPreviewRateLimitException
     */
    public function touch(LegacyPreviewSession $session): void
    {
        $now = CarbonImmutable::now();

        if ($session->cooldown_until instanceof CarbonImmutable && $session->cooldown_until->greaterThan($now)) {
            $seconds = $now->diffInSeconds($session->cooldown_until, false);

            throw new LegacyPreviewRateLimitException($seconds, $session->cooldown_until);
        }

        if ($session->cooldown_until && $session->cooldown_until->lessThanOrEqualTo($now)) {
            $session->cooldown_until = null;
        }

        $windowStart = $session->window_started_at instanceof CarbonImmutable
            ? $session->window_started_at
            : CarbonImmutable::make($session->window_started_at);

        if (! $windowStart || $windowStart->addSeconds($this->windowSeconds)->lessThanOrEqualTo($now)) {
            $session->window_started_at = $now;
            $session->window_count = 0;
        }

        if ($session->window_count >= $this->maxMessages) {
            $cooldownEnd = $now->addSeconds($this->cooldownSeconds);
            $session->cooldown_until = $cooldownEnd;

            throw new LegacyPreviewRateLimitException($this->cooldownSeconds, $cooldownEnd);
        }

        $session->window_count++;
        $session->message_count++;
        $session->last_interaction_at = $now;
    }
}
