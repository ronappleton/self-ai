<?php

namespace App\Support\Observability;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Queue;
use RuntimeException;

class MetricCollector
{
    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $queues = $this->collectQueueMetrics();
        $gpu = $this->collectGpuMetrics();
        $refusals = $this->collectRefusalMetrics();

        return [
            'collected_at' => now()->toIso8601String(),
            'queues' => $queues,
            'gpu' => $gpu,
            'refusals' => $refusals,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function collectQueueMetrics(): array
    {
        $config = config('observability.queues', []);
        $metrics = [];

        foreach ($config as $name => $definition) {
            $connection = $definition['connection'] ?? config('queue.default');
            $queueName = $definition['queue'] ?? $name;

            $size = $this->determineQueueSize($connection, $queueName);

            $metrics[] = [
                'name' => $name,
                'connection' => $connection,
                'queue' => $queueName,
                'depth' => $size,
                'status' => $size === null ? 'unknown' : 'ok',
            ];
        }

        return $metrics;
    }

    private function determineQueueSize(string $connection, string $queue): ?int
    {
        try {
            $connectionInstance = Queue::connection($connection);
        } catch (RuntimeException $exception) {
            return null;
        }

        if (method_exists($connectionInstance, 'size')) {
            try {
                return (int) $connectionInstance->size($queue);
            } catch (RuntimeException $exception) {
                return null;
            }
        }

        if (method_exists($connectionInstance, 'getRedis')) {
            try {
                /** @var \Illuminate\Redis\Connections\Connection $redis */
                $redis = $connectionInstance->getRedis();
                return (int) $redis->connection()->llen($connectionInstance->getQueue($queue));
            } catch (RuntimeException $exception) {
                return null;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function collectGpuMetrics(): array
    {
        $path = config('observability.gpu_metrics_path');

        if ($path && file_exists($path)) {
            $contents = file_get_contents($path);
            if ($contents !== false) {
                $decoded = json_decode($contents, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return [
                        'status' => 'ok',
                        'metrics' => $decoded,
                    ];
                }
            }
        }

        return [
            'status' => 'unavailable',
            'metrics' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function collectRefusalMetrics(): array
    {
        $pattern = (string) config('observability.refusal_audit_pattern', 'refusal');
        $since = now()->subDay();

        $query = AuditLog::query()
            ->where('created_at', '>=', $since)
            ->where(function ($query) use ($pattern) {
                $query->where('action', 'like', "%{$pattern}%")
                    ->orWhere('context->status', 'refused');
            });

        $total = $query->count();

        return [
            'window_hours' => 24,
            'count' => $total,
        ];
    }
}
