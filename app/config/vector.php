<?php

return [
    'driver' => env('VECTOR_DRIVER', 'python'),
    'dimension' => (int) env('VECTOR_EMBED_DIMENSION', 384),

    'python' => [
        'binary' => env('EMBED_WORKER_PYTHON', 'python3'),
        'script' => base_path('worker-embed/main.py'),
        'index_path' => storage_path('app/vector-store/index.faiss.enc'),
        'meta_path' => storage_path('app/vector-store/meta.json.enc'),
        'encryption_key' => env('VECTOR_INDEX_KEY'),
        'timeout' => env('VECTOR_PROCESS_TIMEOUT', 60),
    ],

    'array' => [
        'dimension' => env('VECTOR_EMBED_DIMENSION', 384),
    ],

    'chunking' => [
        'size' => (int) env('VECTOR_CHUNK_SIZE', 800),
        'overlap' => (int) env('VECTOR_CHUNK_OVERLAP', 160),
    ],

    'search' => [
        'default_limit' => (int) env('VECTOR_SEARCH_LIMIT', 5),
        'max_limit' => (int) env('VECTOR_SEARCH_MAX_LIMIT', 20),
        'default_freshness_weight' => (float) env('VECTOR_FRESHNESS_WEIGHT', 0.2),
        'freshness_half_life_days' => (int) env('VECTOR_FRESHNESS_HALF_LIFE', 30),
    ],
];
