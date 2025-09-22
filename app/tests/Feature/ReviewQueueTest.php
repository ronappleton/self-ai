<?php

namespace Tests\Feature;

use App\Models\Consent;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_queue_lists_pending_documents(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->create([
            'source' => 'notebook',
            'sanitized_content' => 'sanitized text',
        ]);

        $this->actingAs($user);

        $response = $this->get('/review/documents/');
        $response->assertOk();
        $response->assertSee('Ingestion Review Queue');
        $response->assertSee($document->source);
    }

    public function test_approving_document_updates_status_and_consent(): void
    {
        $reviewer = User::factory()->create();
        $document = Document::factory()->create();
        $consent = Consent::factory()->create([
            'document_id' => $document->id,
            'source' => $document->source,
            'scope' => $document->consent_scope,
        ]);

        $this->actingAs($reviewer);

        $response = $this->post("/review/documents/{$document->id}/approve", [
            'notes' => 'Looks good',
        ]);

        $response->assertRedirect('/review/documents');

        $document->refresh();
        $consent->refresh();

        $this->assertSame('approved', $document->status);
        $this->assertNotNull($document->approved_at);
        $this->assertSame($reviewer->id, $document->reviewed_by);
        $this->assertSame('approved', $consent->status);
        $this->assertNotNull($consent->granted_at);
        $this->assertSame('Looks good', $consent->notes);
    }

    public function test_rejecting_document_requires_reason_and_updates_records(): void
    {
        $reviewer = User::factory()->create();
        $document = Document::factory()->create();
        $consent = Consent::factory()->create([
            'document_id' => $document->id,
            'source' => $document->source,
            'scope' => $document->consent_scope,
        ]);

        $this->actingAs($reviewer);

        $response = $this->post("/review/documents/{$document->id}/reject", [
            'reason' => 'PII detected',
        ]);

        $response->assertRedirect('/review/documents');

        $document->refresh();
        $consent->refresh();

        $this->assertSame('rejected', $document->status);
        $this->assertNotNull($document->rejected_at);
        $this->assertSame('PII detected', $document->rejection_reason);
        $this->assertSame('rejected', $consent->status);
        $this->assertNull($consent->granted_at);
        $this->assertSame('PII detected', $consent->notes);
    }

    public function test_reviewing_non_pending_document_returns_conflict(): void
    {
        $reviewer = User::factory()->create();
        $document = Document::factory()->create([
            'status' => 'approved',
        ]);

        $this->actingAs($reviewer);

        $response = $this->post("/review/documents/{$document->id}/approve");
        $response->assertStatus(409);
    }
}
