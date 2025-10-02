<?php

namespace Tests\Feature;

use App\Models\Build;
use App\Models\RfcProposal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RfcBuildTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_rfc_proposal(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $payload = [
            'title' => 'Improve ingestion queue throughput',
            'scope' => 'Optimize job batching and reduce queue starvation.',
            'risks' => 'Potential ingestion delay if batch size too large.',
            'tests' => [
                ['name' => 'lint', 'command' => 'vendor/bin/pint --test'],
                ['name' => 'unit', 'command' => 'php artisan test'],
            ],
            'budget' => 3,
        ];

        $response = $this->postJson('/api/v1/rfc', $payload);

        $response->assertCreated();
        $response->assertJsonFragment([
            'title' => $payload['title'],
            'status' => 'draft',
            'budget' => 3,
        ]);

        $this->assertDatabaseHas('rfc_proposals', [
            'title' => $payload['title'],
            'status' => 'draft',
        ]);
    }

    public function test_build_pipeline_writes_reports_and_returns_links(): void
    {
        Storage::fake('minio');

        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $rfc = RfcProposal::factory()->create();

        $payload = [
            'rfc_id' => $rfc->id,
            'diff' => [
                'summary' => 'Add build pipeline orchestrator',
                'files' => [
                    ['path' => 'app/Support/Builds/BuildProcessor.php', 'change' => 'added'],
                ],
            ],
            'test_report' => [
                'static_analysis' => ['status' => 'passed', 'summary' => 'No lint errors.'],
                'unit' => ['status' => 'passed', 'summary' => 'All unit tests passed.'],
                'e2e' => ['status' => 'skipped', 'summary' => 'No e2e coverage yet.'],
                'performance' => ['status' => 'passed', 'summary' => 'No regressions detected.'],
            ],
            'rollback_plan' => 'Revert deployment and restore previous artefacts.',
            'artefacts' => [
                ['name' => 'coverage', 'path' => 'reports/coverage/index.html'],
            ],
        ];

        $response = $this->postJson('/api/v1/build', $payload);
        $response->assertCreated();

        $build = Build::firstOrFail();
        $response->assertJson([
            'build_id' => $build->id,
            'status' => 'passed',
        ]);

        $this->assertNotNull($build->diff_path);
        Storage::disk('minio')->assertExists($build->diff_path);
        Storage::disk('minio')->assertExists($build->test_report_path);
        Storage::disk('minio')->assertExists($build->artefacts_path);
        Storage::disk('minio')->assertExists($build->metadata['manifest_path']);
        $this->assertSame('minio', $build->metadata['manifest_disk']);
    }

    public function test_tripwire_blocks_policy_changes(): void
    {
        Storage::fake('minio');

        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $rfc = RfcProposal::factory()->create();

        $response = $this->postJson('/api/v1/build', [
            'rfc_id' => $rfc->id,
            'diff' => [
                'summary' => 'Attempt to edit immutable policy',
                'files' => [
                    ['path' => 'policy/immutable-policy.yaml', 'change' => 'modified'],
                ],
            ],
            'test_report' => [
                'static_analysis' => ['status' => 'passed'],
                'unit' => ['status' => 'passed'],
                'e2e' => ['status' => 'passed'],
                'performance' => ['status' => 'passed'],
            ],
            'rollback_plan' => 'Rollback not applicable.',
        ]);

        $response->assertCreated();
        $response->assertJson([
            'status' => 'blocked',
            'status_reason' => 'tripwire:policy',
        ]);
    }

    public function test_tripwire_blocks_auth_changes(): void
    {
        Storage::fake('minio');

        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $rfc = RfcProposal::factory()->create();

        $response = $this->postJson('/api/v1/build', [
            'rfc_id' => $rfc->id,
            'diff' => [
                'summary' => 'Adjust auth configuration',
                'files' => [
                    ['path' => 'config/auth.php', 'change' => 'modified'],
                ],
            ],
            'test_report' => [
                'static_analysis' => ['status' => 'passed'],
                'unit' => ['status' => 'passed'],
                'e2e' => ['status' => 'passed'],
                'performance' => ['status' => 'passed'],
            ],
            'rollback_plan' => 'Restore previous auth config.',
        ]);

        $response->assertCreated();
        $response->assertJson([
            'status' => 'blocked',
            'status_reason' => 'tripwire:auth',
        ]);
    }

    public function test_tripwire_blocks_network_changes(): void
    {
        Storage::fake('minio');

        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $rfc = RfcProposal::factory()->create();

        $response = $this->postJson('/api/v1/build', [
            'rfc_id' => $rfc->id,
            'diff' => [
                'summary' => 'Update broadcasting configuration',
                'files' => [
                    ['path' => 'config/broadcasting.php', 'change' => 'modified'],
                ],
            ],
            'test_report' => [
                'static_analysis' => ['status' => 'passed'],
                'unit' => ['status' => 'passed'],
                'e2e' => ['status' => 'passed'],
                'performance' => ['status' => 'passed'],
            ],
            'rollback_plan' => 'Restore network configuration.',
        ]);

        $response->assertCreated();
        $response->assertJson([
            'status' => 'blocked',
            'status_reason' => 'tripwire:network',
        ]);
    }

    public function test_failed_checks_mark_build_as_failed(): void
    {
        Storage::fake('minio');

        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $rfc = RfcProposal::factory()->create();

        $response = $this->postJson('/api/v1/build', [
            'rfc_id' => $rfc->id,
            'diff' => [
                'summary' => 'Introduce regression for testing',
                'files' => [
                    ['path' => 'app/Support/Chat/ChatService.php', 'change' => 'modified'],
                ],
            ],
            'test_report' => [
                'static_analysis' => ['status' => 'passed'],
                'unit' => ['status' => 'failed', 'summary' => '1 failing test'],
                'e2e' => ['status' => 'skipped'],
                'performance' => ['status' => 'passed'],
            ],
            'rollback_plan' => 'Revert to previous commit.',
        ]);

        $response->assertCreated();
        $response->assertJson([
            'status' => 'failed',
            'status_reason' => 'checks_failed',
        ]);

        $build = Build::firstOrFail();
        $this->assertSame('failed', $build->status);
    }

    public function test_can_fetch_build_details(): void
    {
        Storage::fake('minio');

        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $rfc = RfcProposal::factory()->create();

        $payload = [
            'rfc_id' => $rfc->id,
            'diff' => [
                'summary' => 'Document build output',
                'files' => [
                    ['path' => 'docs/builds.md', 'change' => 'added'],
                ],
            ],
            'test_report' => [
                'static_analysis' => ['status' => 'passed'],
                'unit' => ['status' => 'passed'],
                'e2e' => ['status' => 'skipped'],
                'performance' => ['status' => 'passed'],
            ],
            'rollback_plan' => 'Redeploy previous artefacts.',
            'artefacts' => [
                ['name' => 'playwright-report', 'path' => 'run-1/screenshots'],
            ],
        ];

        $createResponse = $this->postJson('/api/v1/build', $payload);
        $buildId = $createResponse->json('build_id');

        $showResponse = $this->getJson("/api/v1/build/{$buildId}");
        $showResponse->assertOk();
        $showResponse->assertJson([
            'build_id' => $buildId,
            'status' => 'passed',
        ]);

        $this->assertNotNull($showResponse->json('diff.files'));
        $this->assertNotNull($showResponse->json('test_report.unit'));
        $this->assertNotNull($showResponse->json('artefacts'));
        $this->assertSame('storage/app/tmp/playwright/run-1/screenshots', $showResponse->json('artefacts.0.path'));
        $this->assertSame('passed', $showResponse->json('manifest.status'));
        $this->assertSame('Redeploy previous artefacts.', $showResponse->json('manifest.rollback_plan'));
        $this->assertSame('passed', $showResponse->json('manifest.tests.static_analysis.status'));
    }
}
