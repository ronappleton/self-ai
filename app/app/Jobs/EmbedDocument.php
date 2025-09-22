<?php

namespace App\Jobs;

use App\Models\Document;
use App\Models\Memory;
use App\Support\Memory\EmbeddingStoreManager;
use App\Support\Memory\TextChunker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class EmbedDocument implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(private readonly string $documentId)
    {
        $this->onQueue('embeddings');
    }

    /**
     * Execute the job.
     */
    public function handle(EmbeddingStoreManager $manager, TextChunker $chunker): void
    {
        /** @var Document|null $document */
        $document = Document::query()->with('memories')->find($this->documentId);

        if (! $document || $document->status !== 'approved') {
            return;
        }

        $text = $document->sanitized_content;
        if (! is_string($text) || trim($text) === '') {
            Log::channel('stack')->info('embed_document.skip', [
                'document_id' => $document->id,
                'reason' => 'empty_sanitized_content',
            ]);

            return;
        }

        $store = $manager->driver();

        $existingVectorIds = $document->memories->pluck('vector_id')->filter()->all();
        if ($existingVectorIds !== []) {
            $store->removeVectors($existingVectorIds);
            $document->memories()->delete();
        }

        $chunkSize = config('vector.chunking.size', 800);
        $chunkOverlap = config('vector.chunking.overlap', 160);
        $chunks = $chunker->chunk($text, $chunkSize, $chunkOverlap);

        if ($chunks === []) {
            return;
        }

        foreach ($chunks as $index => $chunk) {
            $content = $chunk['content'];
            $offset = $chunk['offset'];
            $memory = Memory::create([
                'document_id' => $document->id,
                'chunk_index' => $index,
                'chunk_offset' => $offset,
                'chunk_length' => mb_strlen($content, 'UTF-8'),
                'chunk_text' => $content,
                'source' => $document->source,
                'embedding_hash' => hash('sha256', $content),
                'metadata' => [
                    'tags' => $document->tags,
                    'consent_scope' => $document->consent_scope,
                ],
            ]);

            $vectorId = $store->addMemory($memory, $content);
            $memory->vector_id = $vectorId;
            $memory->save();
        }
    }
}
