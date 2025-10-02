<?php

return [
    'queues' => [
        'default' => [
            'connection' => env('QUEUE_CONNECTION', 'redis'),
            'queue' => 'default',
        ],
        'embeddings' => [
            'connection' => env('QUEUE_CONNECTION', 'redis'),
            'queue' => 'embeddings',
        ],
        'audio-asr' => [
            'connection' => env('QUEUE_CONNECTION', 'redis'),
            'queue' => 'audio-asr',
        ],
        'audio-tts' => [
            'connection' => env('QUEUE_CONNECTION', 'redis'),
            'queue' => 'audio-tts',
        ],
    ],
    'gpu_metrics_path' => env('GPU_METRICS_PATH', storage_path('app/metrics/gpu.json')),
    'refusal_audit_pattern' => 'refusal',
];
