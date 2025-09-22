<?php

namespace Tests\Feature\Audio;

use App\Models\AudioTranscription;
use App\Models\TtsRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AudioEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('minio');
        config([
            'audio.storage_disk' => 'minio',
            'audio.asr.dispatch' => 'sync',
            'audio.tts.dispatch' => 'sync',
        ]);
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
}
