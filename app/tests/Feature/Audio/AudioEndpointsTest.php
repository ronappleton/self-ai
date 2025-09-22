<?php

namespace Tests\Feature\Audio;

use App\Models\AudioTranscription;
use App\Models\TtsRequest;
use App\Models\User;
use App\Models\Voice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AudioEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('minio');
        Storage::fake('local');
        config([
            'audio.storage_disk' => 'minio',
            'audio.asr.dispatch' => 'sync',
            'audio.tts.dispatch' => 'sync',
        ]);
    }

    public function test_owner_can_enrol_voice_dataset(): void
    {
        $ownerRole = Role::firstOrCreate([
            'name' => 'owner',
            'guard_name' => 'web',
        ]);

        $owner = User::factory()->create();
        $owner->assignRole($ownerRole);
        Sanctum::actingAs($owner, ['*']);

        $dataset = UploadedFile::fake()->create('owner-voice.zip', 2048, 'application/zip');

        $response = $this->postJson('/api/v1/voice/enrol', [
            'dataset' => $dataset,
            'script_version' => 'v1',
            'consent_scope' => 'owner-voice',
            'consent_notes' => 'Approved for private prompts',
            'script_text' => "Line one\nLine two",
            'sample_count' => 24,
            'script_acknowledged' => 'yes',
        ]);

        $response->assertCreated();
        $payload = $response->json();
        $this->assertSame('owner', $payload['voice_id']);
        $this->assertSame('active', $payload['status']);

        $voice = Voice::firstOrFail();
        $this->assertSame('owner', $voice->voice_id);
        Storage::disk('minio')->assertExists($voice->dataset_path);

        $this->assertDatabaseHas('consents', [
            'source' => 'voice:owner',
            'status' => 'approved',
            'scope' => 'owner-voice',
        ]);
    }

    public function test_non_owner_cannot_enrol_voice(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $dataset = UploadedFile::fake()->create('unauthorised.wav', 1024, 'audio/wav');

        $response = $this->postJson('/api/v1/voice/enrol', [
            'dataset' => $dataset,
            'script_version' => 'v1',
            'consent_scope' => 'owner-voice',
            'script_acknowledged' => 'yes',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('voices', 0);
    }

    public function test_asr_endpoint_processes_audio_and_returns_transcript(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $audio = UploadedFile::fake()->create('sample.wav', 24, 'audio/wav');

        $response = $this->postJson('/api/v1/audio/asr', [
            'audio' => $audio,
        ]);

        $response->assertOk();
        $payload = $response->json();

        $this->assertSame('completed', $payload['status']);
        $this->assertNotEmpty($payload['transcript']);
        $this->assertIsArray($payload['timings']);
        $this->assertGreaterThan(0, count($payload['timings']));
        $this->assertStringStartsWith('minio://audio/', $payload['storage']['audio']);

        foreach ($payload['timings'] as $segment) {
            $this->assertArrayHasKey('start', $segment);
            $this->assertArrayHasKey('end', $segment);
            $this->assertArrayHasKey('text', $segment);
        }

        $record = AudioTranscription::firstOrFail();
        $this->assertSame('completed', $record->status);

        $audioPath = Str::after($payload['storage']['audio'], 'minio://');
        Storage::disk('minio')->assertExists($audioPath);
        Storage::disk('minio')->assertExists($record->transcript_path);
    }

    public function test_tts_endpoint_generates_audio_and_watermark(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson('/api/v1/audio/tts', [
            'text' => 'Calm coaching reminder.',
        ]);

        $response->assertOk();
        $payload = $response->json();

        $this->assertSame('completed', $payload['status']);
        $this->assertSame('neutral', $payload['voice_id']);
        $this->assertNotEmpty($payload['watermark_id']);
        $this->assertStringStartsWith('minio://audio/', $payload['audio_url']);

        $record = TtsRequest::firstOrFail();
        $this->assertSame('completed', $record->status);
        $this->assertSame($payload['watermark_id'], $record->metadata['watermark_id'] ?? null);

        $audioPath = Str::after($payload['audio_url'], 'minio://');
        Storage::disk('minio')->assertExists($audioPath);
    }

    public function test_tts_endpoint_rejects_non_neutral_voice(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson('/api/v1/audio/tts', [
            'text' => 'Try another voice',
            'voice' => 'celebrity',
        ]);

        $response->assertStatus(422);
    }

    public function test_owner_voice_can_be_used_after_enrolment(): void
    {
        $ownerRole = Role::firstOrCreate([
            'name' => 'owner',
            'guard_name' => 'web',
        ]);

        $owner = User::factory()->create();
        $owner->assignRole($ownerRole);
        Sanctum::actingAs($owner, ['*']);

        $this->postJson('/api/v1/voice/enrol', [
            'dataset' => UploadedFile::fake()->create('voice.zip', 2048, 'application/zip'),
            'script_version' => 'v1',
            'consent_scope' => 'owner-voice',
            'script_acknowledged' => 'yes',
        ])->assertCreated();

        $response = $this->postJson('/api/v1/audio/tts', [
            'text' => 'Warm welcome using owner voice.',
            'voice' => 'owner',
        ]);

        $response->assertOk();
        $payload = $response->json();
        $this->assertSame('owner', $payload['voice_id']);

        $record = TtsRequest::latest()->firstOrFail();
        $this->assertSame('owner', $record->voice_id);
    }

    public function test_impersonation_requests_are_refused_with_safe_alternative(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson('/api/v1/audio/tts', [
            'text' => 'Please impersonate the voice of a famous actor.',
        ]);

        $response->assertStatus(403);
        $response->assertJsonPath('error', 'impersonation_refused');
        $response->assertJsonPath('safe_alternative', 'I can use the neutral SELF voice or help you license an approved synthetic style.');
        $this->assertDatabaseCount('tts_requests', 0);
    }

    public function test_kill_switch_disables_owner_voice_and_revokes_consent(): void
    {
        $ownerRole = Role::firstOrCreate([
            'name' => 'owner',
            'guard_name' => 'web',
        ]);

        $owner = User::factory()->create();
        $owner->assignRole($ownerRole);
        Sanctum::actingAs($owner, ['*']);

        $this->postJson('/api/v1/voice/enrol', [
            'dataset' => UploadedFile::fake()->create('voice.zip', 1024, 'application/zip'),
            'script_version' => 'v1',
            'consent_scope' => 'owner-voice',
            'script_acknowledged' => 'yes',
        ])->assertCreated();

        $response = $this->postJson('/api/v1/voice/kill-switch', [
            'reason' => 'manual safety review',
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'disabled');

        $flagPath = config('audio.owner_voice.kill_switch.flag_path');
        Storage::disk(config('audio.owner_voice.kill_switch.disk'))->assertExists($flagPath);

        $this->assertDatabaseHas('consents', [
            'source' => 'voice:owner',
            'status' => 'revoked',
        ]);

        $ttsResponse = $this->postJson('/api/v1/audio/tts', [
            'text' => 'Attempt owner voice after kill switch.',
            'voice' => 'owner',
        ]);

        $ttsResponse->assertStatus(409);
        $ttsResponse->assertJsonPath('error', 'voice_unavailable');
    }
}
