<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolicyEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_policy_verify_endpoint_returns_signed_metadata(): void
    {
        $response = $this->getJson('/api/policy/verify');

        $response->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('policy.id', 'self-immutable-policy')
            ->assertJsonPath('policy.version', 1);

        $policyPath = base_path(config('policy.immutable_path'));
        $expectedHash = hash('sha256', file_get_contents($policyPath));

        $this->assertSame($expectedHash, $response->json('policy.hash'));
    }
}
