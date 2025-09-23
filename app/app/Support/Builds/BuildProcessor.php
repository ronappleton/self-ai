<?php

namespace App\Support\Builds;

use App\Models\Build;
use App\Models\RfcProposal;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BuildProcessor
{
    public function __construct(private readonly TripwireDetector $tripwireDetector)
    {
    }

    /**
     * @param  array{diff: array<string, mixed>, test_report: array<string, mixed>, rollback_plan: string, artefacts?: array<int, array<string, mixed>>}  $payload
     */
    public function run(RfcProposal $rfc, Build $build, array $payload): Build
    {
        $diskName = config('builds.storage_disk', 'minio');
        $basePath = trim(config('builds.base_path', 'builds'), '/');
        $disk = Storage::disk($diskName);

        $buildPath = $basePath.'/'.Str::lower($rfc->id).'/'.Str::lower($build->id);
        $diff = Arr::get($payload, 'diff', []);
        $testReport = Arr::get($payload, 'test_report', []);
        $artefacts = Arr::get($payload, 'artefacts', []);
        $artefacts = $this->normaliseArtefacts($artefacts);
        $rollbackPlan = Arr::get($payload, 'rollback_plan');

        $tripwire = $this->tripwireDetector->detect(Arr::get($diff, 'files', []));
        $status = 'passed';
        $statusReason = null;

        if ($tripwire !== null) {
            $status = 'blocked';
            $statusReason = 'tripwire:'.$tripwire['category'];
        } else {
            foreach ($testReport as $report) {
                if (! is_array($report)) {
                    continue;
                }

                $result = Str::lower((string) ($report['status'] ?? ''));
                if (in_array($result, ['failed', 'error'], true)) {
                    $status = 'failed';
                    $statusReason = 'checks_failed';
                    break;
                }
            }
        }

        $diffPath = $this->storeJson($disk, $buildPath.'/diff.json', [
            'summary' => Arr::get($diff, 'summary'),
            'files' => Arr::get($diff, 'files', []),
        ]);

        $reportPath = $this->storeJson($disk, $buildPath.'/reports/tests.json', $testReport);
        $artefactsPath = $this->storeJson($disk, $buildPath.'/artefacts.json', $artefacts);

        $manifest = [
            'playwright_base_path' => config('builds.playwright.base_path', 'storage/app/tmp/playwright'),
            'rfc_id' => $rfc->id,
            'build_id' => $build->id,
            'status' => $status,
            'status_reason' => $statusReason,
            'generated_at' => now()->toIso8601String(),
            'summary' => Arr::get($diff, 'summary'),
            'rollback_plan' => $rollbackPlan,
            'tests' => $this->summariseTests($testReport),
        ];
        $this->storeJson($disk, $buildPath.'/manifest.json', $manifest);

        $metadata = [
            'playwright_base_path' => config('builds.playwright.base_path', 'storage/app/tmp/playwright'),
            'summary' => Arr::get($diff, 'summary'),
            'rollback_plan' => $rollbackPlan,
            'tests' => $this->summariseTests($testReport),
            'tripwire' => $tripwire,
        ];

        $build->fill([
            'status' => $status,
            'status_reason' => $statusReason,
            'diff_disk' => $diskName,
            'diff_path' => $diffPath,
            'test_report_disk' => $diskName,
            'test_report_path' => $reportPath,
            'artefacts_disk' => $diskName,
            'artefacts_path' => $artefactsPath,
            'metadata' => $metadata,
        ])->save();

        return $build->fresh();
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    private function summariseTests(array $report): array
    {
        $summary = [];

        foreach ($report as $name => $result) {
            if (! is_array($result)) {
                continue;
            }

            $summary[(string) $name] = [
                'status' => Str::lower((string) ($result['status'] ?? 'unknown')),
                'summary' => $result['summary'] ?? null,
            ];
        }

        return $summary;
    }

    private function storeJson(Filesystem $disk, string $path, mixed $data): string
    {
        $disk->put($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return $path;
    }

    /**
     * @param  array<int, mixed>  $artefacts
     * @return array<int, array{name: string, path: string}>
     */
    private function normaliseArtefacts(array $artefacts): array
    {
        $basePath = config('builds.playwright.base_path', 'storage/app/tmp/playwright');
        $normalised = [];

        foreach ($artefacts as $entry) {
            $name = (string) ($entry['name'] ?? 'artefact');
            $path = (string) ($entry['path'] ?? '');
            $identifier = Str::lower($name.' '.$path);

            if ($path !== '' && str_contains($identifier, 'playwright') && ! str_starts_with($path, $basePath)) {
                $path = rtrim($basePath, '/').'/'.ltrim($path, '/');
            }

            $normalised[] = [
                'name' => $name,
                'path' => $path,
            ];
        }

        return $normalised;
    }
}
