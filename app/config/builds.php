<?php

return [
    'storage_disk' => env('BUILDS_STORAGE_DISK', 'minio'),
    'base_path' => env('BUILDS_STORAGE_PATH', 'builds'),

    'tripwires' => [
        'policy' => [
            'policy/',
        ],
        'auth' => [
            'config/auth.php',
            'app/Providers/AuthServiceProvider.php',
            'app/Http/Middleware/Authenticate.php',
            'app/Http/Middleware/EnsureFrontendRequestsAreStateful.php',
            'app/Auth/',
        ],
        'network' => [
            'config/broadcasting.php',
            'config/reverb.php',
            'app/Providers/BroadcastServiceProvider.php',
            'routes/channels.php',
        ],
    ],

    'playwright' => [
        'base_path' => env('PLAYWRIGHT_ARTIFACT_DIR', 'storage/app/tmp/playwright'),
    ],
];
