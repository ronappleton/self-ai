<?php

namespace App\Support\Chat;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Memory\MemorySearchService;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChatService
{
    public function __construct(
        private readonly MemorySearchService $memorySearch,
        private readonly ChatBudgetManager $budgetManager,
        private readonly TopicBlocker $topicBlocker
    ) {
    }

    /**
     * @param  array<string, mixed>  $controls
     * @return array<string, mixed>
     */
    public function respond(?User $user, string $mode, string $prompt, array $controls = []): array
    {
        $explanationLevel = $this->resolveExplanationLevel($controls['explanation'] ?? null);
        $blocked = $this->topicBlocker->detect($prompt);

        if ($blocked) {
            $snapshot = $this->budgetManager->snapshot($user?->id);
            $this->logRefusal($user, $prompt, $blocked);

            return [
                'status' => 'refused',
                'mode' => $mode,
                'reply' => $this->formatRefusalMessage($blocked),
                'citations' => [],
                'why_card' => $this->refusalWhyCard($mode, $blocked),
                'budget' => $snapshot,
            ];
        }

        $tokenEstimate = $this->estimateTokens($prompt);
        $secondEstimate = $this->estimateSeconds($tokenEstimate);
        $budget = $this->budgetManager->consume($user?->id, $tokenEstimate, $secondEstimate);

        $memoryLimit = (int) config('chat.search.memory_limit', 5);
        $hits = $this->memorySearch->search($prompt, ['limit' => $memoryLimit]);
        $citations = $this->buildCitations($hits);
        $reply = $this->composeReply($mode, $prompt, $hits, $citations, $explanationLevel);
        $whyCard = $this->buildWhyCard($mode, $explanationLevel, $hits, $citations, $tokenEstimate, $secondEstimate);

        return [
            'status' => 'ok',
            'mode' => $mode,
            'reply' => $reply,
            'citations' => $citations,
            'why_card' => $whyCard,
            'budget' => $budget,
        ];
    }

    private function resolveExplanationLevel(?string $value): string
    {
        $levels = config('chat.explanation.levels', ['terse', 'detailed']);
        $default = config('chat.explanation.default', 'detailed');

        if (! is_string($value)) {
            return $default;
        }

        $value = Str::lower($value);

        return in_array($value, $levels, true) ? $value : $default;
    }

    /**
     * @param  array{topic: string, message: string, safe_alternative: string}  $block
     */
    private function formatRefusalMessage(array $block): string
    {
        return trim($block['message'].' '.$block['safe_alternative']);
    }

    /**
     * @param  array{topic: string, message: string, safe_alternative: string}  $block
     * @return array<string, mixed>
     */
    private function refusalWhyCard(string $mode, array $block): array
    {
        return [
            'mode' => $mode,
            'detail_level' => 'terse',
            'summary' => sprintf('Refused: %s guidance is blocked for safety.', ucfirst($block['topic'])),
            'safety' => [
                'blocked_topic' => $block['topic'],
                'message' => $block['message'],
            ],
            'next_step' => $block['safe_alternative'],
            'citations_considered' => 0,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildCitations(Collection $hits): array
    {
        return $hits
            ->values()
            ->map(function (array $hit, int $index): array {
                return [
                    'id' => 'c'.($index + 1),
                    'document_id' => $hit['document_id'] ?? null,
                    'source' => $hit['source_id'] ?? null,
                    'excerpt' => Str::limit((string) ($hit['chunk'] ?? ''), 220),
                    'score' => isset($hit['score']) ? round((float) $hit['score'], 4) : null,
                    'timestamp' => $hit['ts'] ?? null,
                ];
            })
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $citations
     */
    private function composeReply(string $mode, string $prompt, Collection $hits, array $citations, string $explanationLevel): string
    {
        $modeDefinition = Arr::get(config('chat.modes'), $mode, []);
        $lead = Arr::get($modeDefinition, 'lead', Str::title($mode));
        $tone = Arr::get($modeDefinition, 'tone', 'supportive');

        $lines = [];
        $promptSummary = Str::limit($prompt, $explanationLevel === 'terse' ? 120 : 180);
        $lines[] = 'You asked: "'.$promptSummary.'"';
        $insights = $hits->take($explanationLevel === 'terse' ? 1 : 3)->values();

        foreach ($insights as $index => $hit) {
            $citationId = Arr::get($citations, $index.'.id');
            $excerpt = Str::limit((string) ($hit['chunk'] ?? ''), $explanationLevel === 'terse' ? 140 : 220);
            $lines[] = sprintf('Insight %d: %s%s', $index + 1, $excerpt, $citationId ? " [{$citationId}]" : '');
        }

        if ($insights->isEmpty()) {
            $lines[] = 'I do not have indexed memories on this yet. Let us capture more context before I advise further.';
        }

        $closing = match ($mode) {
            'coach' => 'Let us pick one next step you can take confidently. I can help you break it down.',
            'analyst' => 'Consider the evidence above and decide what data you need next. I can help outline experiments or metrics.',
            'listener' => 'I am here to keep reflecting with you. Share more if something still feels unresolved.',
            default => 'Let me know how you would like to continue and I will stay aligned with your boundaries.',
        };

        if ($explanationLevel === 'terse') {
            $closing = Str::before($closing, '.').' when you are ready.';
        }

        return sprintf(
            "%s (%s)\n%s\n\n%s",
            $lead,
            $tone,
            implode(PHP_EOL, array_map(fn ($line) => '- '.$line, $lines)),
            $closing
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $citations
     * @return array<string, mixed>
     */
    private function buildWhyCard(string $mode, string $explanationLevel, Collection $hits, array $citations, int $tokens, int $seconds): array
    {
        $summary = $explanationLevel === 'terse'
            ? 'Quick take grounded in the top memory snippet I found relevant.'
            : 'Combined your prompt with consented memories to craft a grounded response with context for next steps.';

        return [
            'mode' => $mode,
            'detail_level' => $explanationLevel,
            'summary' => $summary,
            'memory' => [
                'considered' => $hits->count(),
                'used_citations' => array_column($citations, 'id'),
                'top_scores' => $hits->take(3)->map(fn ($hit) => isset($hit['score']) ? round((float) $hit['score'], 4) : null)->filter()->values()->all(),
            ],
            'safety' => [
                'topic_blocks_checked' => array_keys(config('chat.topic_blocks', [])),
                'refused' => false,
            ],
            'budget' => [
                'prompt_tokens_estimate' => $tokens,
                'processing_seconds_estimate' => $seconds,
            ],
        ];
    }

    private function estimateTokens(string $prompt): int
    {
        $words = str_word_count($prompt);
        $tokens = (int) ceil($words * 1.2);

        return max(1, $tokens);
    }

    private function estimateSeconds(int $tokens): int
    {
        return max(1, (int) ceil($tokens / 120));
    }

    /**
     * @param  array{topic: string, message: string, safe_alternative: string}  $block
     */
    private function logRefusal(?User $user, string $prompt, array $block): void
    {
        $timestamp = now();
        $actor = $user?->email ?? 'system';
        $context = [
            'topic' => $block['topic'],
            'message' => $block['message'],
            'prompt_preview' => Str::limit($prompt, 180),
        ];

        DB::transaction(function () use ($timestamp, $actor, $context): void {
            $query = AuditLog::query()->latest('id');
            if (DB::connection()->getDriverName() !== 'sqlite') {
                $query->lockForUpdate();
            }

            $previousHash = $query->value('hash');
            $payload = [
                'actor' => $actor,
                'action' => 'chat.refusal',
                'target' => 'chat',
                'context' => $context,
                'previous_hash' => $previousHash,
                'created_at' => $timestamp->toIso8601String(),
            ];

            $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

            AuditLog::query()->create([
                'actor' => $actor,
                'action' => 'chat.refusal',
                'target' => 'chat',
                'context' => $context,
                'hash' => $hash,
                'previous_hash' => $previousHash,
                'created_at' => $timestamp,
            ]);
        });
    }
}
