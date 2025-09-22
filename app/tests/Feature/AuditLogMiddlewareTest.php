<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        AuditLog::query()->delete();
    }

    public function test_request_appends_audit_log_entry(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertOk();

        $log = AuditLog::query()->first();

        $this->assertNotNull($log);
        $this->assertSame('GET api/health', $log->action);
        $this->assertNotEmpty($log->hash);
        $this->assertNull($log->previous_hash);
    }

    public function test_hash_chain_links_sequential_entries(): void
    {
        $this->getJson('/api/health');
        $this->getJson('/api/policy/verify');

        $logs = AuditLog::query()->orderBy('id')->get();

        $this->assertGreaterThanOrEqual(2, $logs->count());
        $first = $logs->first();
        $second = $logs->get(1);

        $this->assertNull($first->previous_hash);
        $this->assertSame($first->hash, $second->previous_hash);
    }
}
