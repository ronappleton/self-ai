<?php

return [
    'verifier' => [
        'keys' => array_filter([
            env('PROMOTION_VERIFIER_KEY_ID', 'local') => env('PROMOTION_VERIFIER_KEY'),
        ], static fn ($value) => $value !== null && $value !== ''),
        'max_skew' => (int) env('PROMOTION_VERIFIER_MAX_SKEW', 120),
        'max_ttl' => (int) env('PROMOTION_VERIFIER_MAX_TTL', 900),
    ],

    'canary' => [
        'targets' => [
            'api-health' => [
                'method' => 'GET',
                'url' => env('PROMOTION_CANARY_API_URL', env('APP_URL', 'http://localhost').'/api/health'),
                'expect_status' => 200,
            ],
        ],
        'attempts' => (int) env('PROMOTION_CANARY_ATTEMPTS', 3),
        'delay_seconds' => (int) env('PROMOTION_CANARY_DELAY', 5),
        'timeout' => (float) env('PROMOTION_CANARY_TIMEOUT', 10.0),
    ],
];
