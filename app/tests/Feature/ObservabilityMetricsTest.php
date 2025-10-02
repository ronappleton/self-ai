<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ObservabilityMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_observability_metrics_endpoint_returns_snapshot(): void
    {
        config([
            'observability.queues' => [
                'default' => [
                    'connection' => 'sync',
                    'queue' => 'default',
                ],
            ],
        ]);

        $path = storage_path('app/metrics/gpu.json');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        file_put_contents($path, json_encode([
            'gpu_utilization' => 42,
        ]));

        AuditLog::query()->create([
            'actor' => 'system',
            'action' => 'chat.refusal',
            'target' => 'test',
            'context' => ['status' => 'refused'],
            'hash' => 'hash',
            'previous_hash' => null,
            'created_at' => now(),
        ]);

        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson('/api/v1/observability/metrics');

        $response->assertOk();
        $response->assertJsonStructure([
            'live' => [
                'collected_at',
                'queues' => [[
                    'name',
                    'connection',
                    'queue',
                    'depth',
                    'status',
                ]],
                'gpu' => ['status', 'metrics'],
                'refusals' => ['window_hours', 'count'],
            ],
        ]);

        $this->assertSame(1, $response->json('live.refusals.count'));
        $this->assertSame('ok', $response->json('live.gpu.status'));
    }
}
