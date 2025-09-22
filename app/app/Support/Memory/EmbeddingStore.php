<?php

namespace App\Support\Memory;

use App\Models\Memory;

interface EmbeddingStore
{
    /**
     * Add the given memory chunk to the vector store.
     */
    public function addMemory(Memory $memory, string $text): int;

    /**
     * Remove the provided vector identifiers from the store.
     *
     * @param array<int, int> $vectorIds
     */
    public function removeVectors(array $vectorIds): void;

    /**
     * Perform a similarity search against the store.
     *
     * @return list<array{vector_id:int,score:float}>
     */
    public function search(string $query, int $limit): array;
}
