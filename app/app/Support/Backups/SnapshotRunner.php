<?php

namespace App\Support\Backups;

use App\Models\BackupSnapshot;
use Illuminate\Process\ProcessResult;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class SnapshotRunner
{
    /**
     * @return array<int, BackupSnapshot>
     */
    public function run(): array
    {
        $components = config('backups.components', []);
        $snapshots = [];

        foreach ($components as $name => $definition) {
            $snapshots[] = $this->runComponent((string) $name, $definition);
        }

        return $snapshots;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function runComponent(string $name, array $definition): BackupSnapshot
    {
        $snapshot = new BackupSnapshot([
            'component' => $name,
            'rotation_tier' => Arr::get($definition, 'rotation_tier'),
            'status' => 'pending',
            'started_at' => now(),
        ]);

        $snapshot->save();

        $snapshotCommand = Arr::get($definition, 'snapshot_command');
        $restoreCommand = Arr::get($definition, 'restore_command');

        $metadata = [];
        $status = 'success';
        $completedAt = now();

        if ($snapshotCommand) {
            $result = $this->runCommand($snapshotCommand);
            $metadata['snapshot'] = $this->formatProcessResult($result);
            if (! $result->successful()) {
                $status = 'failed';
            } else {
                $snapshot->snapshot_path = $this->extractPathFromOutput($result->output());
            }
        } else {
            $status = 'failed';
            $metadata['snapshot'] = [
                'status' => 'failed',
                'message' => 'Snapshot command not configured.',
            ];
        }

        if ($status === 'success' && $restoreCommand) {
            $restoreResult = $this->runCommand($restoreCommand);
            $metadata['restore'] = $this->formatProcessResult($restoreResult);
            if ($restoreResult->successful()) {
                $snapshot->restore_verified_at = now();
            } else {
                $status = 'failed';
            }
        } elseif ($status === 'success') {
            $status = 'failed';
            $metadata['restore'] = [
                'status' => 'failed',
                'message' => 'Restore command not configured.',
            ];
        }

        $snapshot->status = $status;
        $snapshot->metadata = $metadata;
        $snapshot->completed_at = $completedAt;
        $snapshot->save();

        return $snapshot->fresh();
    }

    /**
     * @param  array<int, string>|string  $command
     */
    private function runCommand($command): ProcessResult
    {
        if (is_array($command)) {
            return Process::run($command);
        }

        if (Str::startsWith($command, '[')) {
            $decoded = json_decode($command, true);
            if (is_array($decoded)) {
                return Process::run($decoded);
            }
        }

        return Process::run($command);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatProcessResult(ProcessResult $result): array
    {
        return [
            'exit_code' => $result->exitCode(),
            'status' => $result->successful() ? 'success' : 'failed',
            'output' => Str::limit(trim($result->output()), 500),
            'error_output' => Str::limit(trim($result->errorOutput()), 500),
        ];
    }

    private function extractPathFromOutput(string $output): ?string
    {
        $lines = array_filter(array_map('trim', explode(PHP_EOL, $output)));

        foreach ($lines as $line) {
            if (Str::startsWith($line, 'snapshot:')) {
                return trim(Str::after($line, 'snapshot:'));
            }
        }

        return null;
    }
}
