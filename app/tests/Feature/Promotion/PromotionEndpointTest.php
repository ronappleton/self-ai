<?php

namespace Tests\Feature\Promotion;

use App\Models\AuditLog;
use App\Models\Build;
use App\Models\User;
use App\Support\Promotions\PromotionSignatureVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PromotionEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'promotion.verifier.keys' => ['verifier-1' => 'very-secret-key'],
            'promotion.canary.targets' => [
                'api-health' => [
                    'url' => 'https://health.test/check',
                    'method' => 'GET',
                    'expect_status' => 200,
                ],
            ],
        ]);
    }

    public function test_promotion_requires_valid_signature(): void
    {
        Carbon::setTestNow('2025-01-01T12:00:00Z');

        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $build = Build::factory()->create();

        $payload = [
            'build_id' => $build->id,
            'verifier_id' => 'verifier-1',
            'nonce' => (string) Str::uuid(),
            'requested_at' => Carbon::now()->toIso8601String(),
            'expires_at' => Carbon::now()->addMinutes(5)->toIso8601String(),
            'signature' => base64_encode('invalid'),
        ];

        $response = $this->postJson('/api/v1/promote', $payload);
        $response->assertStatus(403);
        $response->assertJsonPath('error', 'invalid_signature');

        $this->assertDatabaseCount('promotions', 0);
        $this->assertTrue(
            AuditLog::query()->where('action', 'promotion.denied')->exists(),
            'Denied promotion should be recorded in the audit log.'
        );
    }

    public function test_successful_promotion_runs_canary_checks(): void
    {
        Carbon::setTestNow('2025-02-02T10:15:00Z');

        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $build = Build::factory()->create();

        Http::fake([
            'https://health.test/*' => Http::response(['status' => 'ok'], 200),
        ]);

        $payload = [
            'build_id' => $build->id,
            'verifier_id' => 'verifier-1',
            'nonce' => (string) Str::uuid(),
            'requested_at' => Carbon::now()->toIso8601String(),
            'expires_at' => Carbon::now()->addMinutes(5)->toIso8601String(),
            'canary' => [
                'targets' => ['api-health'],
            ],
        ];

        $payload['signature'] = PromotionSignatureVerifier::signPayload('very-secret-key', [
            'build_id' => $payload['build_id'],
            'verifier_id' => $payload['verifier_id'],
            'nonce' => $payload['nonce'],
            'requested_at' => $payload['requested_at'],
            'expires_at' => $payload['expires_at'],
        ]);

        $response = $this->postJson('/api/v1/promote', $payload);
        $response->assertStatus(202);
        $response->assertJsonPath('status', 'promoted');
        $response->assertJsonPath('canary.status', 'passed');
        $response->assertJsonPath('rollback_triggered', false);

        $this->assertDatabaseHas('promotions', [
            'build_id' => $build->id,
            'status' => 'promoted',
            'canary_status' => 'passed',
        ]);

        $this->assertTrue(
            AuditLog::query()->where('action', 'promotion.promoted')->exists(),
            'Successful promotion should be logged.'
        );

        $build->refresh();
        $this->assertSame('promoted', $build->metadata['promotion']['status']);
    }

    public function test_failed_canary_triggers_rollback_and_logs(): void
    {
        Carbon::setTestNow('2025-03-03T09:00:00Z');

        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $build = Build::factory()->create();

        Http::fake([
            'https://health.test/*' => Http::sequence()
                ->push(['status' => 'not-ok'], 500)
                ->push(['status' => 'still-bad'], 500),
        ]);

        $payload = [
            'build_id' => $build->id,
            'verifier_id' => 'verifier-1',
            'nonce' => (string) Str::uuid(),
            'requested_at' => Carbon::now()->toIso8601String(),
            'expires_at' => Carbon::now()->addMinutes(5)->toIso8601String(),
        ];

        $payload['signature'] = PromotionSignatureVerifier::signPayload('very-secret-key', [
            'build_id' => $payload['build_id'],
            'verifier_id' => $payload['verifier_id'],
            'nonce' => $payload['nonce'],
            'requested_at' => $payload['requested_at'],
            'expires_at' => $payload['expires_at'],
        ]);

        $response = $this->postJson('/api/v1/promote', $payload);
        $response->assertStatus(409);
        $response->assertJsonPath('status', 'rolled_back');
        $response->assertJsonPath('rollback_triggered', true);

        $this->assertDatabaseHas('promotions', [
            'build_id' => $build->id,
            'status' => 'rolled_back',
            'canary_status' => 'failed',
            'rollback_triggered' => true,
        ]);

        $this->assertTrue(
            AuditLog::query()->where('action', 'promotion.rollback')->exists(),
            'Rollback should be logged in the audit trail.'
        );
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
