<?php

namespace App\Console\Commands;

use App\Models\SecurityReport;
use App\Support\Security\CisBaselineChecker;
use App\Support\Security\DependencyReportGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SecurityBaselineReportCommand extends Command
{
    protected $signature = 'security:baseline-report';

    protected $description = 'Run CIS-style baseline checks and dependency audits.';

    public function handle(CisBaselineChecker $checker, DependencyReportGenerator $generator): int
    {
        $baselineResults = $checker->run();
        $dependencyReports = $generator->generate();

        $status = $this->determineStatus($baselineResults, $dependencyReports);
        $summary = $this->buildSummary($baselineResults, $dependencyReports);

        SecurityReport::query()->create([
            'status' => $status,
            'baseline_results' => $baselineResults,
            'dependency_reports' => $dependencyReports,
            'summary' => $summary,
            'generated_at' => now(),
        ]);

        $this->info("Security baseline report stored ({$status}).");
        $this->line($summary);

        return $status === 'fail' ? static::FAILURE : static::SUCCESS;
    }

    /**
     * @param  array<int, array<string, mixed>>  $baseline
     * @param  array<string, array<string, mixed>>  $dependencies
     */
    private function determineStatus(array $baseline, array $dependencies): string
    {
        if (collect($baseline)->contains(fn ($result) => ($result['status'] ?? '') === 'fail')) {
            return 'fail';
        }

        if (collect($dependencies)->contains(fn ($result) => ($result['status'] ?? '') === 'failed')) {
            return 'fail';
        }

        if (collect($baseline)->contains(fn ($result) => ($result['status'] ?? '') === 'warn')) {
            return 'warn';
        }

        if (collect($dependencies)->contains(fn ($result) => ($result['status'] ?? '') === 'skipped')) {
            return 'warn';
        }

        return 'pass';
    }

    /**
     * @param  array<int, array<string, mixed>>  $baseline
     * @param  array<string, array<string, mixed>>  $dependencies
     */
    private function buildSummary(array $baseline, array $dependencies): string
    {
        $failedBaseline = collect($baseline)
            ->filter(fn ($result) => ($result['status'] ?? '') === 'fail')
            ->map(fn ($result) => $result['key'] ?? 'unknown')
            ->all();

        $failedDependencies = collect($dependencies)
            ->filter(fn ($result) => ($result['status'] ?? '') === 'failed')
            ->keys()
            ->all();

        $parts = [];

        if (! empty($failedBaseline)) {
            $parts[] = 'Baseline failures: '.implode(', ', $failedBaseline);
        }

        if (! empty($failedDependencies)) {
            $parts[] = 'Dependency scan failures: '.implode(', ', $failedDependencies);
        }

        if (empty($parts)) {
            $parts[] = 'All checks completed';
        }

        return Str::limit(implode(' | ', $parts), 240);
    }
}
