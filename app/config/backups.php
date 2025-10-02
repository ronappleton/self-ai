<?php

return [
    'components' => [
        'sql' => [
            'rotation_tier' => 'hot',
            'snapshot_command' => env('BACKUP_SQL_COMMAND', base_path('scripts/backups/backup-sql.sh')),
            'restore_command' => env('RESTORE_SQL_COMMAND', base_path('scripts/backups/restore-sql.sh')),
        ],
        'vectors' => [
            'rotation_tier' => 'warm',
            'snapshot_command' => env('BACKUP_VECTORS_COMMAND', base_path('scripts/backups/backup-vectors.sh')),
            'restore_command' => env('RESTORE_VECTORS_COMMAND', base_path('scripts/backups/restore-vectors.sh')),
        ],
        'minio' => [
            'rotation_tier' => 'cold',
            'snapshot_command' => env('BACKUP_MINIO_COMMAND', base_path('scripts/backups/backup-minio.sh')),
            'restore_command' => env('RESTORE_MINIO_COMMAND', base_path('scripts/backups/restore-minio.sh')),
        ],
    ],
    'rotation' => [
        'hot' => [
            'retention_days' => 7,
            'description' => 'Primary on-site snapshots (daily).',
        ],
        'warm' => [
            'retention_days' => 30,
            'description' => 'Secondary storage with weekly verification.',
        ],
        'cold' => [
            'retention_days' => 180,
            'description' => 'Off-site/offline rotation per 3-2-1 practice.',
        ],
    ],
];
