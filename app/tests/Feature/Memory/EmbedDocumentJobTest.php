<?php

namespace Tests\Feature\Memory;

use App\Jobs\EmbedDocument;
use App\Models\Document;
use App\Models\Memory;
use App\Support\Memory\Drivers\ArrayEmbeddingStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmbedDocumentJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['vector.driver' => 'array']);
        ArrayEmbeddingStore::reset();
    }

    public function test_it_creates_memories_for_an_approved_document(): void
    {
        $document = Document::factory()->create([
            'status' => 'approved',
            'approved_at' => now(),
            'sanitized_content' => 'First sentence about consent. Second sentence about secure storage.',
            'tags' => ['consent', 'storage'],
        ]);

        EmbedDocument::dispatchSync($document->id);

        $memories = Memory::where('document_id', $document->id)->get();
        $this->assertGreaterThanOrEqual(1, $memories->count());
        $this->assertNotNull($memories->first()?->vector_id);
        $this->assertSame('consent', $memories->first()?->metadata['tags'][0] ?? null);
    }

    public function test_it_skips_documents_without_sanitized_text(): void
    {
        $document = Document::factory()->create([
            'status' => 'approved',
            'approved_at' => now(),
            'sanitized_content' => null,
        ]);

        EmbedDocument::dispatchSync($document->id);

        $this->assertDatabaseCount('memories', 0);
    }
}
