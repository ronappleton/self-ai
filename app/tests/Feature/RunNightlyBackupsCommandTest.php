<?php

namespace Tests\Feature;

use App\Models\BackupSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class RunNightlyBackupsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_nightly_backups_command_records_successful_snapshots(): void
    {
        config([
            'backups.components' => [
                'sql' => [
                    'rotation_tier' => 'hot',
                    'snapshot_command' => ['echo', 'snapshot:/backups/sql.sql'],
                    'restore_command' => ['echo', 'restore-ok'],
                ],
            ],
        ]);

        Process::fake([
            'echo snapshot:/backups/sql.sql' => Process::result("snapshot:/backups/sql.sql\n", '', 0),
            'echo restore-ok' => Process::result("restore-ok\n", '', 0),
        ]);

        $exitCode = Artisan::call('backups:run-nightly');

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseCount('backup_snapshots', 1);

        $snapshot = BackupSnapshot::query()->first();
        $this->assertNotNull($snapshot);
        $this->assertSame('success', $snapshot->status);
        $this->assertSame('/backups/sql.sql', $snapshot->snapshot_path);
        $this->assertNotNull($snapshot->restore_verified_at);
    }
}
