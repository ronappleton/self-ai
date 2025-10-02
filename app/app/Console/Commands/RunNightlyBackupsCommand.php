<?php

namespace App\Console\Commands;

use App\Support\Backups\SnapshotRunner;
use Illuminate\Console\Command;

class RunNightlyBackupsCommand extends Command
{
    protected $signature = 'backups:run-nightly';

    protected $description = 'Run nightly snapshots across SQL, vectors, and MinIO.';

    public function handle(SnapshotRunner $runner): int
    {
        $snapshots = $runner->run();

        foreach ($snapshots as $snapshot) {
            $this->line(sprintf(
                '%s [%s] status=%s path=%s',
                $snapshot->component,
                $snapshot->rotation_tier ?? 'tier-unset',
                $snapshot->status,
                $snapshot->snapshot_path ?? 'n/a'
            ));
        }

        $failed = collect($snapshots)->contains(fn ($snapshot) => $snapshot->status !== 'success');

        if ($failed) {
            $this->error('One or more snapshots failed. Check metadata for details.');

            return static::FAILURE;
        }

        $this->info('Nightly snapshots completed successfully.');

        return static::SUCCESS;
    }
}
