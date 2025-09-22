<?php

namespace App\Support\Chat;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

class ChatBudgetManager
{
    public function __construct(private readonly CacheRepository $cache)
    {
    }

    /**
     * Consume the requested budget and return the latest snapshot.
     *
     * @param  int<0, max>  $tokens
     * @param  int<0, max>  $seconds
     * @return array{
     *     tokens: array{limit: int, used: int, remaining: int, reset_at: string},
     *     seconds: array{limit: int, used: int, remaining: int, reset_at: string}
     * }
     */
    public function consume(?int $userId, int $tokens, int $seconds): array
    {
        $snapshot = $this->snapshot($userId);
        $limits = config('chat.budget');

        if (($limits['tokens']['daily_limit'] ?? 0) > 0) {
            $limit = (int) $limits['tokens']['daily_limit'];
            $used = $snapshot['tokens']['used'];

            if ($used + $tokens > $limit) {
                $snapshot['tokens']['requested'] = $tokens;
                throw new ChatBudgetExceededException('tokens', $snapshot);
            }

            $this->cache->put(
                $this->tokenCacheKey($userId),
                $used + $tokens,
                CarbonImmutable::now()->endOfDay()
            );
        }

        if (($limits['seconds']['per_minute_limit'] ?? 0) > 0) {
            $limit = (int) $limits['seconds']['per_minute_limit'];
            $used = $snapshot['seconds']['used'];

            if ($used + $seconds > $limit) {
                $snapshot['seconds']['requested'] = $seconds;
                throw new ChatBudgetExceededException('seconds', $snapshot);
            }

            $this->cache->put(
                $this->secondsCacheKey($userId),
                $used + $seconds,
                CarbonImmutable::now()->startOfMinute()->addMinute()
            );
        }

        return $this->snapshot($userId);
    }

    /**
     * Get the current budget snapshot without consuming.
     *
     * @return array{
     *     tokens: array{limit: int, used: int, remaining: int, reset_at: string},
     *     seconds: array{limit: int, used: int, remaining: int, reset_at: string}
     * }
     */
    public function snapshot(?int $userId): array
    {
        $limits = config('chat.budget');
        $tokenLimit = (int) ($limits['tokens']['daily_limit'] ?? 0);
        $secondsLimit = (int) ($limits['seconds']['per_minute_limit'] ?? 0);

        $usedTokens = (int) $this->cache->get($this->tokenCacheKey($userId), 0);
        $usedSeconds = (int) $this->cache->get($this->secondsCacheKey($userId), 0);

        $tokenReset = CarbonImmutable::now()->endOfDay();
        $secondsReset = CarbonImmutable::now()->startOfMinute()->addMinute();

        return [
            'tokens' => [
                'limit' => $tokenLimit,
                'used' => min($usedTokens, $tokenLimit > 0 ? $tokenLimit : $usedTokens),
                'remaining' => max(0, $tokenLimit - $usedTokens),
                'reset_at' => $tokenReset->toIso8601String(),
            ],
            'seconds' => [
                'limit' => $secondsLimit,
                'used' => min($usedSeconds, $secondsLimit > 0 ? $secondsLimit : $usedSeconds),
                'remaining' => max(0, $secondsLimit - $usedSeconds),
                'reset_at' => $secondsReset->toIso8601String(),
            ],
        ];
    }

    private function tokenCacheKey(?int $userId): string
    {
        $userKey = $this->userKey($userId);

        return "chat:budget:tokens:{$userKey}:".CarbonImmutable::now()->format('Y-m-d');
    }

    private function secondsCacheKey(?int $userId): string
    {
        $userKey = $this->userKey($userId);

        return "chat:budget:seconds:{$userKey}:".CarbonImmutable::now()->format('Y-m-d-H-i');
    }

    private function userKey(?int $userId): string
    {
        return $userId ? 'user-'.$userId : 'anonymous';
    }
}
