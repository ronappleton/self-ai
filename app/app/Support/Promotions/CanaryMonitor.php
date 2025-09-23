<?php

namespace App\Support\Promotions;

use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

class CanaryMonitor
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $targets;

    private int $attempts;

    private int $delaySeconds;

    private float $timeout;

    /**
     * @param  array{targets?: array<string, array<string, mixed>>, attempts?: int, delay_seconds?: int, timeout?: float}|null  $config
     */
    public function __construct(?array $config = null)
    {
        $config ??= config('promotion.canary', []);
        $this->targets = (array) Arr::get($config, 'targets', []);
        $this->attempts = max(1, (int) Arr::get($config, 'attempts', 3));
        $this->delaySeconds = max(0, (int) Arr::get($config, 'delay_seconds', 5));
        $this->timeout = max(1, (float) Arr::get($config, 'timeout', 10));
    }

    /**
     * @param  array<int, string>  $requestedTargets
     */
    public function run(array $requestedTargets = []): CanaryReport
    {
        $targets = $this->resolveTargets($requestedTargets);

        $checks = [];
        $allPassed = true;

        foreach ($targets as $name => $target) {
            $result = $this->checkTarget($name, $target);
            $checks[$name] = $result;
            if (($result['status'] ?? 'failed') !== 'ok') {
                $allPassed = false;
            }
        }

        return new CanaryReport($allPassed ? 'passed' : 'failed', $checks);
    }

    /**
     * @param  array<string, mixed>  $target
     * @return array<string, mixed>
     */
    private function checkTarget(string $name, array $target): array
    {
        $url = (string) Arr::get($target, 'url', '');
        $method = strtoupper((string) Arr::get($target, 'method', 'GET'));
        $expectedStatus = (int) Arr::get($target, 'expect_status', 200);

        $history = [];
        $status = 'failed';

        for ($attempt = 1; $attempt <= $this->attempts; $attempt++) {
            $entry = [
                'attempt' => $attempt,
                'timestamp' => Carbon::now()->toIso8601String(),
            ];

            try {
                $response = Http::timeout($this->timeout)->acceptJson()->{$method}($url);
                $entry['status_code'] = $response->status();
                $entry['ok'] = $response->status() === $expectedStatus;

                if ($entry['ok']) {
                    $status = 'ok';
                    $history[] = $entry;
                    break;
                }
            } catch (\Throwable $exception) {
                $entry['error'] = $exception->getMessage();
            }

            $history[] = $entry;
        }

        return [
            'status' => $status,
            'target' => $name,
            'url' => $url,
            'expected_status' => $expectedStatus,
            'attempts' => count($history),
            'history' => $history,
            'delay_seconds' => $this->delaySeconds,
        ];
    }

    /**
     * @param  array<int, string>  $requested
     * @return array<string, array<string, mixed>>
     */
    private function resolveTargets(array $requested): array
    {
        if ($requested === []) {
            return $this->targets;
        }

        $resolved = [];
        foreach ($requested as $name) {
            $name = (string) $name;
            if (isset($this->targets[$name])) {
                $resolved[$name] = $this->targets[$name];
            }
        }

        if ($resolved === []) {
            return $this->targets;
        }

        return $resolved;
    }
}
