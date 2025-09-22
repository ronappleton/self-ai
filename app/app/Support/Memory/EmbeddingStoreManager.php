<?php

namespace App\Support\Memory;

use App\Support\Memory\Drivers\ArrayEmbeddingStore;
use App\Support\Memory\Drivers\PythonEmbeddingStore;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

class EmbeddingStoreManager
{
    public function __construct(private readonly Container $container)
    {
    }

    public function driver(?string $name = null): EmbeddingStore
    {
        $name ??= config('vector.driver', 'python');

        return match ($name) {
            'python' => $this->container->make(PythonEmbeddingStore::class),
            'array' => $this->container->make(ArrayEmbeddingStore::class),
            default => throw new InvalidArgumentException("Unsupported embedding store driver [{$name}]."),
        };
    }

    public function addMemory(EmbeddingStore $store, ...$arguments): int
    {
        return $store->addMemory(...$arguments);
    }
}
