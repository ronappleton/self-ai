<?php

namespace App\Support\Memory;

use App\Models\Memory;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class MemorySearchService
{
    public function __construct(private readonly EmbeddingStoreManager $manager)
    {
    }

    /**
     * @param array<string, mixed> $options
     */
    public function search(string $query, array $options = []): Collection
    {
        $limit = (int) ($options['limit'] ?? config('vector.search.default_limit'));
        $limit = max(1, min($limit, (int) config('vector.search.max_limit', 20)));
        $freshnessWeight = (float) ($options['freshness_weight'] ?? config('vector.search.default_freshness_weight'));
        $sourceWeights = collect($options['source_weights'] ?? [])
            ->mapWithKeys(fn ($value, $key) => [mb_strtolower((string) $key) => (float) $value]);

        $store = $this->manager->driver();
        $rawResults = $store->search($query, $limit * 3);
        if ($rawResults === []) {
            return collect();
        }

        $vectorIds = array_column($rawResults, 'vector_id');
        $memories = Memory::with('document')
            ->whereIn('vector_id', $vectorIds)
            ->get()
            ->keyBy('vector_id');

        $halfLife = (int) config('vector.search.freshness_half_life_days', 30);
        $decayFactor = $halfLife > 0 ? log(2) / $halfLife : 0.0;

        $results = collect();
        foreach ($rawResults as $result) {
            $memory = $memories->get($result['vector_id']);
            if (! $memory) {
                continue;
            }

            $document = $memory->document;
            $timestamp = $document?->approved_at ?? $memory->created_at;
            $freshnessScore = $this->freshnessScore($timestamp, $decayFactor);
            $sourceKey = mb_strtolower($memory->source ?? '');
            $sourceWeight = $sourceWeights->get($sourceKey, 1.0);
            $baseScore = (float) $result['score'];
            $finalScore = $baseScore * $sourceWeight + ($freshnessWeight * $freshnessScore);

            $results->push([
                'memory_id' => $memory->id,
                'chunk' => $memory->chunk_text,
                'score' => $finalScore,
                'base_score' => $baseScore,
                'freshness_score' => $freshnessScore,
                'source_id' => $memory->source,
                'document_id' => $memory->document_id,
                'vector_id' => $result['vector_id'],
                'ts' => optional($timestamp)->toIso8601String(),
            ]);
        }

        return $results
            ->sortByDesc('score')
            ->take($limit)
            ->values();
    }

    private function freshnessScore(?CarbonInterface $timestamp, float $decayFactor): float
    {
        if (! $timestamp) {
            return 0.0;
        }

        $ageDays = max(0.0, now()->diffInRealHours($timestamp) / 24);
        if ($decayFactor <= 0.0) {
            return 1.0;
        }

        return exp(-$decayFactor * $ageDays);
    }
}
