<?php

namespace Tests\Feature\Frontend;

use App\Support\Observability\MetricCollector;
use DOMDocument;
use Tests\TestCase;

class ConsoleFrontendTest extends TestCase
{
    public function test_console_page_renders_with_api_endpoints(): void
    {
        $response = $this->get('/console');

        $response->assertOk();

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        @$dom->loadHTML($response->getContent());

        $element = $dom->getElementById('console-app');
        $this->assertNotNull($element);

        $payload = json_decode($element->getAttribute('data-endpoints') ?? '{}', true);
        $this->assertIsArray($payload);
        $this->assertSame(url('/api/health'), $payload['health'] ?? null);
        $this->assertSame(url('/api/v1/chat'), $payload['chat'] ?? null);
        $this->assertSame(url('/api/v1/memory/search'), $payload['memorySearch'] ?? null);
        $this->assertSame(url('/api/v1/ingest/text'), $payload['ingestText'] ?? null);
    }

    public function test_console_can_reach_health_endpoint(): void
    {
        app()->instance(MetricCollector::class, new class extends MetricCollector {
            /**
             * @return array<string, mixed>
             */
            public function snapshot(): array
            {
                return [
                    'collected_at' => now()->toIso8601String(),
                    'queues' => [
                        ['name' => 'default', 'depth' => 0, 'status' => 'ok'],
                    ],
                    'gpu' => ['status' => 'unavailable'],
                    'refusals' => ['window_hours' => 24, 'count' => 0],
                ];
            }
        });

        $response = $this->getJson('/api/health');

        $response->assertOk();
        $response->assertJsonPath('status', 'ok');
        $response->assertJsonStructure([
            'status',
            'timestamp',
            'app',
            'queue',
            'policy_hash',
            'policy_version',
        ]);
    }
}
