<?php

namespace Tests\Feature\Chat;

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\Memory;
use App\Models\User;
use App\Support\Memory\Drivers\ArrayEmbeddingStore;
use App\Support\Memory\EmbeddingStoreManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['vector.driver' => 'array']);
        ArrayEmbeddingStore::reset();
    }

    public function test_chat_returns_coach_response_with_citations_and_why_card(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $document = Document::factory()->create([
            'status' => 'approved',
            'source' => 'journal',
            'sanitized_content' => 'Hydration and short recovery breaks helped last week.',
            'consent_scope' => 'personal-health',
        ]);

        $this->createMemory($document, 'Remember to hydrate before workouts and schedule lighter recovery days.');

        $response = $this->postJson('/api/v1/chat', [
            'mode' => 'coach',
            'prompt' => 'How can I keep my energy up during training this month?',
        ]);

        $response->assertOk();
        $response->assertJsonPath('mode', 'coach');
        $response->assertJsonStructure([
            'status', 'reply', 'citations', 'why_card', 'budget',
        ]);

        $payload = $response->json();
        $this->assertSame('ok', $payload['status']);
        $this->assertStringContainsString('Coach perspective', $payload['reply']);
        $this->assertNotEmpty($payload['citations']);
        $this->assertSame($document->id, $payload['citations'][0]['document_id']);
        $this->assertSame('detailed', $payload['why_card']['detail_level']);
        $this->assertSame(1, $payload['why_card']['memory']['citations_considered']);
        $this->assertGreaterThan(0, $payload['budget']['tokens']['limit']);
        $this->assertArrayHasKey('remaining', $payload['budget']['tokens']);
    }

    public function test_chat_allows_explanation_dial(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $document = Document::factory()->create([
            'status' => 'approved',
            'source' => 'projects',
            'sanitized_content' => 'Weekly review template stored in archives.',
            'consent_scope' => 'work',
        ]);

        $this->createMemory($document, 'Use a quick retrospective: wins, blockers, one improvement.');

        $response = $this->postJson('/api/v1/chat', [
            'mode' => 'analyst',
            'prompt' => 'Give me a fast format for my sprint retro.',
            'controls' => [
                'explanation' => 'terse',
            ],
        ]);

        $response->assertOk();
        $payload = $response->json();

        $this->assertSame('ok', $payload['status']);
        $this->assertSame('terse', $payload['why_card']['detail_level']);
        $this->assertStringContainsString('Analyst brief', $payload['reply']);
        $this->assertStringContainsString('when you are ready', $payload['reply']);
        $this->assertSame(1, $payload['why_card']['memory']['citations_considered']);
    }

    public function test_chat_blocks_high_risk_topics_and_logs_refusal(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson('/api/v1/chat', [
            'mode' => 'listener',
            'prompt' => 'I need a medical diagnosis for chest pain right now.',
        ]);

        $response->assertOk();
        $payload = $response->json();

        $this->assertSame('refused', $payload['status']);
        $this->assertSame('medical', $payload['why_card']['safety']['blocked_topic']);
        $this->assertEmpty($payload['citations']);
        $this->assertStringContainsString('consult a licensed medical professional', $payload['reply']);

        $this->assertTrue(
            AuditLog::query()->where('action', 'chat.refusal')->exists(),
            'Refusal should be recorded in the audit log.'
        );
    }

    public function test_chat_enforces_budget_limits(): void
    {
        config([
            'chat.budget.tokens.daily_limit' => 4,
            'chat.budget.seconds.per_minute_limit' => 120,
        ]);

        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson('/api/v1/chat', [
            'mode' => 'coach',
            'prompt' => 'This prompt should burn through the token budget immediately for testing.',
        ]);

        $response->assertStatus(429);
        $response->assertJsonPath('error', 'budget_exceeded');
        $response->assertJsonPath('budget_type', 'tokens');
        $payload = $response->json();
        $this->assertSame(4, $payload['budget']['tokens']['limit']);
        $this->assertSame(4, $payload['budget']['tokens']['remaining']);
    }

    private function createMemory(Document $document, string $text): void
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
    }
}
