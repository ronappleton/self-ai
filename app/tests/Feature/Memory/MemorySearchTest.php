<?php

namespace Tests\Feature\Memory;

use App\Jobs\EmbedDocument;
use App\Models\Document;
use App\Models\User;
use App\Support\Memory\Drivers\ArrayEmbeddingStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MemorySearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['vector.driver' => 'array']);
        ArrayEmbeddingStore::reset();
    }

    public function test_authenticated_user_can_query_memory_index(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $document = Document::factory()->create([
            'status' => 'approved',
            'approved_at' => now()->subDays(3),
            'sanitized_content' => 'Consent log entry describing family meeting and commitments.',
            'source' => 'family-journal',
        ]);

        EmbedDocument::dispatchSync($document->id);

        $response = $this->getJson('/api/v1/memory/search?q=consent');

        $response->assertOk();
        $response->assertJsonStructure([
            'query',
            'hits' => [[
                'memory_id',
                'chunk',
                'score',
                'base_score',
                'freshness_score',
                'source_id',
                'document_id',
                'vector_id',
                'ts',
            ]],
        ]);
        $this->assertStringContainsString('Consent', $response->json('hits.0.chunk'));
    }

    public function test_source_weighting_adjusts_scores(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $recentDoc = Document::factory()->create([
            'status' => 'approved',
            'approved_at' => now()->subDay(),
            'sanitized_content' => 'Coach session discussing progress.',
            'source' => 'coach-notes',
        ]);

        $olderDoc = Document::factory()->create([
            'status' => 'approved',
            'approved_at' => now()->subWeeks(10),
            'sanitized_content' => 'Personal reflection about progress goals and actions.',
            'source' => 'personal-journal',
        ]);

        EmbedDocument::dispatchSync($recentDoc->id);
        EmbedDocument::dispatchSync($olderDoc->id);

        $response = $this->getJson('/api/v1/memory/search?q=progress&source_weights[personal-journal]=2.5&limit=2');
        $response->assertOk();

        $hits = $response->json('hits');
        $this->assertCount(2, $hits);
        $this->assertSame('personal-journal', $hits[0]['source_id']);
        $this->assertGreaterThanOrEqual($hits[1]['score'], $hits[0]['score']);
    }
}
