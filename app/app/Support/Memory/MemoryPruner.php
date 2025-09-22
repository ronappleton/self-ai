<?php

namespace App\Support\Memory;

use App\Models\Document;
use Illuminate\Support\Collection;

class MemoryPruner
{
    public function __construct(private readonly EmbeddingStoreManager $manager)
    {
    }

    public function forgetDocument(Document $document): void
    {
        $vectorIds = $document->memories()->pluck('vector_id')->filter()->all();
        if ($vectorIds !== []) {
            $this->manager->driver()->removeVectors($vectorIds);
        }

        $document->memories()->delete();
    }

    public function forgetDocuments(Collection $documents): void
    {
        $documents->load('memories');
        $vectorIds = $documents->flatMap(fn (Document $doc) => $doc->memories->pluck('vector_id'))->filter()->values()->all();
        if ($vectorIds !== []) {
            $this->manager->driver()->removeVectors($vectorIds);
        }

        foreach ($documents as $document) {
            $document->memories()->delete();
        }
    }
}
