<?php

namespace App\Support\Legacy;

use App\Models\LegacyPreviewSession;
use App\Models\User;
use App\Support\Chat\TopicBlocker;
use App\Support\Memory\MemorySearchService;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;

class LegacyPreviewService
{
    public function __construct(
        private readonly MemorySearchService $memorySearch,
        private readonly TopicBlocker $topicBlocker,
        private readonly LegacyToneFormatter $toneFormatter,
        private readonly LegacyPreviewRateLimiter $rateLimiter
    ) {
    }

    /**
     * @param  array{
     *     session_id?: string,
     *     persona_name?: string,
     *     prompt: string,
     *     tone?: string,
     *     redactions?: array{memory_ids?: array<int, string>, sources?: array<int, string>, notes?: string}
     * }  $payload
     *
     * @return array<string, mixed>
     */
    public function preview(?User $user, array $payload): array
    {
        $session = $this->resolveSession($user, $payload['session_id'] ?? null);
        $tone = $this->resolveTone($payload['tone'] ?? $session->tone);
        $personaName = $payload['persona_name'] ?? $session->persona_name;
        $redactions = $this->mergeRedactions($session->redactions ?? [], $payload['redactions'] ?? []);

        $session->tone = $tone;
        $session->persona_name = $personaName;
        $session->redactions = $redactions;

        try {
            $this->rateLimiter->touch($session);
        } catch (LegacyPreviewRateLimitException $exception) {
            $session->save();

            throw $exception;
        }

        $prompt = trim($payload['prompt']);
        $blocked = $this->topicBlocker->detect($prompt, config('legacy.topic_blocks', []));

        if ($blocked) {
            $session->save();

            return $this->topicBlockedResponse($session, $blocked);
        }

        $limit = (int) config('legacy.search.memory_limit', 5);
        $hits = $this->memorySearch->search($prompt, ['limit' => $limit]);
        [$filtered, $removed] = $this->applyRedactions($hits, $redactions);
        $citations = $this->buildCitations($filtered);
        $reply = $this->toneFormatter->format($tone, $prompt, $filtered, $citations);

        $session->save();

        return [
            'status' => 'ok',
            'disclosure' => (string) config('legacy.disclosure'),
            'persona_name' => $session->persona_name,
            'tone' => $session->tone,
            'reply' => $reply,
            'citations' => $citations,
            'redactions' => [
                'memory_ids' => array_values($redactions['memory_ids']),
                'sources' => array_values($redactions['sources']),
                'notes' => $redactions['notes'] ?? null,
                'removed' => $removed,
            ],
            'session' => [
                'id' => $session->id,
                'message_count' => $session->message_count,
                'window_count' => $session->window_count,
                'cooldown_until' => optional($session->cooldown_until)->toIso8601String(),
                'last_interaction_at' => optional($session->last_interaction_at)->toIso8601String(),
            ],
        ];
    }

    private function resolveSession(?User $user, ?string $sessionId): LegacyPreviewSession
    {
        if ($sessionId === null) {
            return LegacyPreviewSession::create([
                'user_id' => $user?->id,
                'tone' => config('legacy.default_tone', 'gentle'),
                'redactions' => [
                    'memory_ids' => [],
                    'sources' => [],
                ],
                'window_started_at' => now(),
                'window_count' => 0,
            ]);
        }

        $session = LegacyPreviewSession::query()->find($sessionId);

        if (! $session) {
            throw new RuntimeException('Legacy preview session not found.');
        }

        if ($user && $session->user_id && $session->user_id !== $user->id) {
            throw new RuntimeException('You do not have access to this legacy preview session.');
        }

        return $session;
    }

    /**
     * @param  array{memory_ids?: array<int, string>, sources?: array<int, string>, notes?: string}  $incoming
     * @return array{memory_ids: array<int, string>, sources: array<int, string>, notes?: string}
     */
    private function mergeRedactions(array $current, array $incoming): array
    {
        $memoryIds = array_values(array_unique(array_filter(array_merge(
            $current['memory_ids'] ?? [],
            $incoming['memory_ids'] ?? []
        ), static fn ($value) => is_string($value) && $value !== '')));

        $sources = array_values(array_unique(array_filter(array_map(
            static fn ($value) => Str::lower((string) $value),
            array_merge($current['sources'] ?? [], $incoming['sources'] ?? [])
        ), static fn ($value) => $value !== '')));

        $notes = $incoming['notes'] ?? ($current['notes'] ?? null);

        return [
            'memory_ids' => $memoryIds,
            'sources' => $sources,
            'notes' => $notes,
        ];
    }

    private function resolveTone(?string $tone): string
    {
        $tones = array_keys(config('legacy.tones', []));
        $tone = Str::lower((string) $tone);

        if ($tone === '' || ! in_array($tone, $tones, true)) {
            return config('legacy.default_tone', 'gentle');
        }

        return $tone;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $hits
     * @param  array{memory_ids: array<int, string>, sources: array<int, string>, notes?: string}  $redactions
     * @return array{0: Collection<int, array<string, mixed>>, 1: array<string, int>}
     */
    private function applyRedactions(Collection $hits, array $redactions): array
    {
        $memoryIds = array_map('strtolower', $redactions['memory_ids']);
        $sources = array_map('strtolower', $redactions['sources']);

        $removed = [
            'memory_ids' => 0,
            'sources' => 0,
        ];

        $filtered = $hits->filter(function (array $hit) use ($memoryIds, $sources, &$removed) {
            $memoryId = isset($hit['memory_id']) ? Str::lower((string) $hit['memory_id']) : null;
            if ($memoryId && in_array($memoryId, $memoryIds, true)) {
                $removed['memory_ids']++;

                return false;
            }

            $source = isset($hit['source_id']) ? Str::lower((string) $hit['source_id']) : null;
            if ($source && in_array($source, $sources, true)) {
                $removed['sources']++;

                return false;
            }

            return true;
        })->values();

        return [$filtered, $removed];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $hits
     * @return array<int, array<string, mixed>>
     */
    private function buildCitations(Collection $hits): array
    {
        return $hits
            ->take(5)
            ->values()
            ->map(function (array $hit, int $index): array {
                return [
                    'id' => 'lp'.($index + 1),
                    'document_id' => $hit['document_id'] ?? null,
                    'memory_id' => $hit['memory_id'] ?? null,
                    'source' => $hit['source_id'] ?? null,
                    'excerpt' => Str::limit((string) Arr::get($hit, 'chunk', ''), 220),
                    'score' => isset($hit['score']) ? round((float) $hit['score'], 4) : null,
                    'timestamp' => $hit['ts'] ?? null,
                ];
            })
            ->all();
    }

    /**
     * @param  array{topic: string, message: string, safe_alternative: string}  $block
     * @return array<string, mixed>
     */
    private function topicBlockedResponse(LegacyPreviewSession $session, array $block): array
    {
        $reply = sprintf(
            "%s %s\n\n%s",
            $block['message'],
            $block['safe_alternative'],
            'If you need support processing these feelings, please reach someone you trust or a local helpline.'
        );

        return [
            'status' => 'refused',
            'disclosure' => (string) config('legacy.disclosure'),
            'reason' => 'topic_blocked',
            'blocked_topic' => $block['topic'],
            'safe_alternative' => $block['safe_alternative'],
            'reply' => trim($reply),
            'citations' => [],
            'session' => [
                'id' => $session->id,
                'message_count' => $session->message_count,
                'window_count' => $session->window_count,
                'cooldown_until' => optional($session->cooldown_until)->toIso8601String(),
                'last_interaction_at' => optional($session->last_interaction_at)->toIso8601String(),
            ],
        ];
    }
}
