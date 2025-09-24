<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Memory;
use App\Models\LegacyPreviewSession;
use App\Models\User;
use App\Support\Memory\Drivers\ArrayEmbeddingStore;
use App\Support\Memory\EmbeddingStoreManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LegacyPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['vector.driver' => 'array']);
        ArrayEmbeddingStore::reset();
    }

    public function test_legacy_preview_returns_disclosure_and_applies_redactions(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $keepDocument = Document::factory()->create([
            'status' => 'approved',
            'source' => 'letters',
            'sanitized_content' => 'We walked at sunrise and spoke about gratitude.',
        ]);

        $this->createMemory($keepDocument, 'Morning walks reminded us to breathe and notice the light.');

        $redactedDocument = Document::factory()->create([
            'status' => 'approved',
            'source' => 'private-journal',
            'sanitized_content' => 'Private reflections stored securely.',
        ]);

        $redactedMemory = $this->createMemory($redactedDocument, 'A private note that should not appear in preview.');

        $response = $this->postJson('/api/v1/legacy/preview', [
            'prompt' => 'Can you remind me about our peaceful morning walks?',
            'tone' => 'gentle',
            'persona_name' => 'Grandma',
            'redactions' => [
                'memory_ids' => [$redactedMemory->id],
                'sources' => [$redactedDocument->source],
                'notes' => 'Exclude sensitive journal entries.',
            ],
        ]);

        $response->assertOk();
        $payload = $response->json();

        $this->assertSame('ok', $payload['status']);
        $this->assertSame(config('legacy.disclosure'), $payload['disclosure']);
        $this->assertSame('gentle', $payload['tone']);
        $this->assertSame('Grandma', $payload['persona_name']);
        $this->assertNotEmpty($payload['citations']);

        foreach ($payload['citations'] as $citation) {
            $this->assertNotSame('private-journal', $citation['source']);
        }

        $this->assertGreaterThan(0, $payload['redactions']['removed']['memory_ids']);
        $this->assertContains('private-journal', $payload['redactions']['sources']);
        $this->assertArrayHasKey('id', $payload['session']);
        $this->assertSame(1, $payload['session']['message_count']);
    }

    public function test_legacy_preview_blocks_disallowed_topics(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson('/api/v1/legacy/preview', [
            'prompt' => 'Please provide medical advice about a diagnosis right now.',
        ]);

        $response->assertOk();
        $payload = $response->json();

        $this->assertSame('refused', $payload['status']);
        $this->assertSame('medical', $payload['blocked_topic']);
        $this->assertStringContainsString('medical guidance', $payload['reply']);
        $this->assertEmpty($payload['citations']);
    }

    public function test_legacy_preview_enforces_rate_limit_and_sets_cooldown(): void
    {
        config([
            'legacy.rate_limit.max_messages' => 2,
            'legacy.rate_limit.window_seconds' => 60,
            'legacy.rate_limit.cooldown_seconds' => 120,
        ]);

        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $document = Document::factory()->create([
            'status' => 'approved',
            'source' => 'stories',
            'sanitized_content' => 'Stories worth remembering.',
        ]);

        $this->createMemory($document, 'Remember the laughter during board games.');

        $first = $this->postJson('/api/v1/legacy/preview', [
            'prompt' => 'Share a warm story.',
        ])->json();

        $sessionId = $first['session']['id'];

        $this->postJson('/api/v1/legacy/preview', [
            'session_id' => $sessionId,
            'prompt' => 'Share another gentle reminder.',
        ])->assertOk();

        $third = $this->postJson('/api/v1/legacy/preview', [
            'session_id' => $sessionId,
            'prompt' => 'One more reflection please.',
        ]);

        $third->assertStatus(429);
        $third->assertJsonPath('error', 'rate_limited');
        $this->assertGreaterThan(0, $third->json('retry_after_seconds'));
        $this->assertTrue($third->headers->has('Retry-After'));

        $session = LegacyPreviewSession::findOrFail($sessionId);
        $this->assertNotNull($session->cooldown_until);
    }

    private function createMemory(Document $document, string $text): Memory
    {
        $memory = Memory::create([
            'document_id' => $document->id,
            'vector_id' => null,
            'chunk_index' => 0,
            'chunk_offset' => 0,
            'chunk_length' => mb_strlen($text, 'UTF-8'),
            'chunk_text' => $text,
            'source' => $document->source,
            'embedding_model' => 'hashed-self-1',
            'embedding_hash' => hash('sha256', $text),
            'metadata' => [
                'tags' => $document->tags ?? [],
                'consent_scope' => $document->consent_scope,
            ],
        ]);

        $store = app(EmbeddingStoreManager::class)->driver('array');
        $memory->vector_id = $store->addMemory($memory, $text);
        $memory->save();

        return $memory;
    }
}
