<?php

namespace App\Support\Memory\Drivers;

use App\Models\Memory;
use App\Support\Memory\EmbeddingStore;
use App\Support\Memory\HashedEmbeddingGenerator;
use RuntimeException;

class ArrayEmbeddingStore implements EmbeddingStore
{
    /**
     * @var array<int, list<float>>
     */
    private static array $vectors = [];

    private static int $nextId = 1;

    public function __construct(private readonly HashedEmbeddingGenerator $generator)
    {
    }

    public function addMemory(Memory $memory, string $text): int
    {
        $dimension = config('vector.array.dimension', 384);
        $vector = $this->generator->generate($text, $dimension);
        $vectorId = self::$nextId++;
        self::$vectors[$vectorId] = $vector;

        return $vectorId;
    }

    public function removeVectors(array $vectorIds): void
    {
        foreach ($vectorIds as $id) {
            unset(self::$vectors[$id]);
        }
    }

    public function search(string $query, int $limit): array
    {
        if ($limit < 1) {
            throw new RuntimeException('Search limit must be at least one.');
        }

        $dimension = config('vector.array.dimension', 384);
        $queryVector = $this->generator->generate($query, $dimension);

        $scores = [];
        foreach (self::$vectors as $vectorId => $vector) {
            $scores[] = [
                'vector_id' => $vectorId,
                'score' => $this->generator->similarity($queryVector, $vector),
            ];
        }

        usort($scores, static fn ($a, $b) => $a['score'] <=> $b['score']);
        $scores = array_reverse($scores);

        return array_slice($scores, 0, $limit);
    }

    public static function reset(): void
    {
        self::$vectors = [];
        self::$nextId = 1;
    }
}
