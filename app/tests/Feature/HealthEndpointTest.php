<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_reports_system_status(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonStructure([
                'status',
                'timestamp',
                'app',
                'queue',
                'policy_hash',
                'policy_version',
            ]);

        $policyPath = base_path(config('policy.immutable_path'));
        $expectedHash = hash('sha256', file_get_contents($policyPath));

        $this->assertSame($expectedHash, $response->json('policy_hash'));
    }
}
