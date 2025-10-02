<?php

return [
    'baseline' => [
        'require_app_key' => [
            'description' => 'Application key is configured.',
            'remediation' => 'Run `php artisan key:generate` and set APP_KEY in the environment.',
        ],
        'disable_debug_in_production' => [
            'description' => 'Debug mode is disabled when running in production.',
            'remediation' => 'Set APP_DEBUG=false in production environments.',
        ],
        'https_app_url' => [
            'description' => 'Application URL uses HTTPS.',
            'remediation' => 'Update APP_URL to use https:// and terminate TLS at the edge.',
        ],
        'queue_not_sync' => [
            'description' => 'Queue driver is not using the sync driver.',
            'remediation' => 'Configure QUEUE_CONNECTION=redis to ensure async processing.',
        ],
    ],
    'dependencies' => [
        'tools' => [
            'composer' => [
                'command' => ['composer', 'audit', '--format=json', '--locked'],
                'timeout' => 120,
                'expects_json' => true,
            ],
            'npm' => [
                'command' => ['pnpm', 'audit', '--json'],
                'timeout' => 120,
                'expects_json' => true,
                'working_directory' => base_path(),
            ],
        ],
    ],
];
