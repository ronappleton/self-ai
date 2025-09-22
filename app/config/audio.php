<?php

return [
    'storage_disk' => env('AUDIO_STORAGE_DISK', 'minio'),

    'asr' => [
        'queue' => env('AUDIO_ASR_QUEUE', 'audio-asr'),
        'binary' => env('AUDIO_ASR_PYTHON', 'python3'),
        'script' => env('AUDIO_ASR_SCRIPT', base_path('worker-asr/main.py')),
        'timeout' => (float) env('AUDIO_ASR_TIMEOUT', 120),
        'dispatch' => env('AUDIO_ASR_DISPATCH', 'sync'),
    ],

    'tts' => [
        'queue' => env('AUDIO_TTS_QUEUE', 'audio-tts'),
        'binary' => env('AUDIO_TTS_PYTHON', 'python3'),
        'script' => env('AUDIO_TTS_SCRIPT', base_path('worker-tts/main.py')),
        'timeout' => (float) env('AUDIO_TTS_TIMEOUT', 120),
        'dispatch' => env('AUDIO_TTS_DISPATCH', 'sync'),
        'default_voice' => env('AUDIO_TTS_DEFAULT_VOICE', 'neutral'),
        'sample_rate' => (int) env('AUDIO_TTS_SAMPLE_RATE', 16000),
    ],

    'playwright' => [
        'artifact_root' => env('PLAYWRIGHT_ARTIFACT_ROOT', storage_path('app/tmp/playwright')),
    ],
];
