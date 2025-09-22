<?php

namespace Tests\Feature;

use App\Models\Consent;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class IngestionEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_text_ingestion_scrubs_pii_and_stores_document(): void
    {
        Storage::fake('minio');

        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson('/api/v1/ingest/text', [
            'source' => 'journal',
            'text' => 'Contact me at alice@example.com or 555-123-4567 tomorrow.',
            'consent_scope' => 'personal',
            'tags' => ['journal'],
            'metadata' => ['submitted_via' => 'app'],
            'retention_class' => 'standard',
        ]);

        $response->assertAccepted();
        $response->assertJson(['pii_scrubbed' => true]);

        $document = Document::firstOrFail();

        $this->assertSame('journal', $document->source);
        $this->assertTrue($document->pii_scrubbed);
        $this->assertStringNotContainsString('alice@example.com', $document->sanitized_content);
        $this->assertStringContainsString('[REDACTED:EMAIL]', $document->sanitized_content);
        $this->assertStringContainsString('[REDACTED:PHONE]', $document->sanitized_content);
        Storage::disk('minio')->assertExists($document->storage_path);

        $this->assertDatabaseHas('consents', [
            'document_id' => $document->id,
            'source' => 'journal',
            'status' => 'pending',
        ]);
    }

    public function test_owner_can_bypass_pii_scrubbing(): void
    {
        Storage::fake('minio');

        $ownerRole = Role::firstOrCreate([
            'name' => 'owner',
            'guard_name' => 'web',
        ]);

        $user = User::factory()->create();
        $user->assignRole($ownerRole);
        Sanctum::actingAs($user, ['*']);

        $text = 'Here is my number: 555-000-1111.';

        $response = $this->postJson('/api/v1/ingest/text', [
            'source' => 'notes',
            'text' => $text,
            'consent_scope' => 'owner-only',
            'bypass_pii_scrub' => true,
        ]);

        $response->assertAccepted();
        $response->assertJson(['pii_scrubbed' => false]);

        $document = Document::firstOrFail();
        $this->assertSame($text, $document->sanitized_content);
    }

    public function test_non_owner_cannot_bypass_pii_scrubbing(): void
    {
        Storage::fake('minio');

        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson('/api/v1/ingest/text', [
            'source' => 'notes',
            'text' => 'Sensitive 555-000-1111',
            'consent_scope' => 'owner-only',
            'bypass_pii_scrub' => true,
        ]);

        $response->assertForbidden();
    }

    public function test_file_ingestion_stores_binary_and_metadata(): void
    {
        Storage::fake('minio');

        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $file = UploadedFile::fake()->create('transcript.pdf', 12, 'application/pdf');

        $response = $this->post('/api/v1/ingest/file', [
            'source' => 'archives',
            'file' => $file,
            'consent_scope' => 'personal',
            'metadata' => ['submitted_via' => 'upload'],
        ], ['Accept' => 'application/json']);

        $response->assertAccepted();

        $document = Document::firstOrFail();
        $this->assertSame('file', $document->type);
        $this->assertSame('application/pdf', $document->mime_type);
        Storage::disk('minio')->assertExists($document->storage_path);
    }

    public function test_right_to_forget_by_document_soft_deletes_and_revokes_consent(): void
    {
        Storage::fake('minio');

        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $document = Document::factory()->create([
            'source' => 'journals',
        ]);

        Storage::disk('minio')->put($document->storage_path, 'test');

        Consent::factory()->create([
            'document_id' => $document->id,
            'source' => 'journals',
            'scope' => 'personal',
        ]);

        $response = $this->deleteJson("/api/v1/ingest/document/{$document->id}");
        $response->assertOk();

        $document->refresh();
        $this->assertSame('deleted', $document->status);
        $this->assertSoftDeleted('documents', ['id' => $document->id]);
        $this->assertDatabaseHas('consents', [
            'document_id' => $document->id,
            'status' => 'revoked',
        ]);
    }

    public function test_right_to_forget_by_source(): void
    {
        Storage::fake('minio');

        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $documents = Document::factory()->count(2)->create([
            'source' => 'source-xyz',
        ]);

        foreach ($documents as $document) {
            Storage::disk('minio')->put($document->storage_path, 'test');
            Consent::factory()->create([
                'document_id' => $document->id,
                'source' => 'source-xyz',
                'scope' => 'personal',
            ]);
        }

        $response = $this->deleteJson('/api/v1/ingest/source/source-xyz');
        $response->assertOk();
        $response->assertJson(['documents_removed' => 2]);

        foreach ($documents as $document) {
            $this->assertSoftDeleted('documents', ['id' => $document->id]);
        }
    }
}
