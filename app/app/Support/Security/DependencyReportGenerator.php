<?php

namespace App\Support\Security;

use Illuminate\Process\ProcessResult;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class DependencyReportGenerator
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function generate(): array
    {
        $tools = config('security.dependencies.tools', []);
        $reports = [];

        foreach ($tools as $name => $definition) {
            $reports[$name] = $this->runTool($name, $definition);
        }

        return $reports;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private function runTool(string $name, array $definition): array
    {
        $command = Arr::get($definition, 'command');
        $timeout = (int) Arr::get($definition, 'timeout', 120);
        $expectsJson = (bool) Arr::get($definition, 'expects_json', false);
        $workingDirectory = Arr::get($definition, 'working_directory');

        if (! is_array($command) || empty($command)) {
            return [
                'status' => 'skipped',
                'summary' => 'Command not configured.',
            ];
        }

        $factory = Process::timeout($timeout);
        if ($workingDirectory) {
            $factory = $factory->path($workingDirectory);
        }

        /** @var ProcessResult $result */
        $result = $factory->run($command);

        if (! $result->successful()) {
            return [
                'status' => 'failed',
                'summary' => Str::limit($result->errorOutput() ?: $result->output(), 500),
                'exit_code' => $result->exitCode(),
            ];
        }

        $payload = $result->output();

        if ($expectsJson) {
            $decoded = json_decode($payload, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return [
                    'status' => 'passed',
                    'summary' => $this->summariseJsonReport($decoded),
                    'report' => $decoded,
                ];
            }
        }

        return [
            'status' => 'passed',
            'summary' => Str::limit(trim($payload), 500),
        ];
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function summariseJsonReport(array $report): string
    {
        $vulnerabilityCount = 0;

        if (isset($report['advisories']) && is_array($report['advisories'])) {
            $vulnerabilityCount = count($report['advisories']);
        }

        if (isset($report['metadata']['vulnerabilities'])) {
            $totals = (array) $report['metadata']['vulnerabilities'];
            $vulnerabilityCount = array_sum(array_map('intval', $totals));
        }

        return $vulnerabilityCount === 0
            ? 'No known vulnerabilities reported.'
            : sprintf('%d vulnerabilities reported. Review details.', $vulnerabilityCount);
    }
}
