<?php

namespace Tests\Feature;

use App\Models\SecurityReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class SecurityBaselineReportCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_baseline_report_command_stores_results(): void
    {
        config([
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'app.url' => 'https://self.test',
            'app.debug' => false,
            'queue.default' => 'redis',
            'queue.connections.redis.driver' => 'redis',
            'security.dependencies.tools' => [
                'composer' => [
                    'command' => ['composer', 'audit', '--format=json', '--locked'],
                    'timeout' => 120,
                    'expects_json' => true,
                ],
            ],
        ]);

        Process::fake([
            'composer audit --format=json --locked' => Process::result(json_encode([
                'advisories' => [],
            ]), '', 0),
        ]);

        $exitCode = Artisan::call('security:baseline-report');

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseCount('security_reports', 1);

        $report = SecurityReport::query()->first();
        $this->assertNotNull($report);
        $this->assertSame('pass', $report->status);
        $this->assertArrayHasKey('composer', $report->dependency_reports);
    }
}
