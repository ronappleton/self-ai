<?php

return [
    'modes' => [
        'coach' => [
            'label' => 'Coach',
            'lead' => 'Coach perspective',
            'tone' => 'encouraging and action-focused',
        ],
        'analyst' => [
            'label' => 'Analyst',
            'lead' => 'Analyst brief',
            'tone' => 'structured and neutral',
        ],
        'listener' => [
            'label' => 'Listener',
            'lead' => 'Listener reflection',
            'tone' => 'empathetic and validating',
        ],
    ],

    'explanation' => [
        'default' => 'detailed',
        'levels' => ['terse', 'detailed'],
    ],

    'topic_blocks' => [
        'medical' => [
            'keywords' => [
                'diagnos', 'prescrib', 'medical advice', 'treatment plan', 'symptom',
                'medication', 'dose', 'therapy recommendation', 'disease',
            ],
            'message' => 'I am not a medical professional and cannot help with clinical guidance.',
            'safe_alternative' => 'Please consult a licensed medical professional who can review your situation directly. I can help you prepare questions for that conversation or explore general wellness habits.',
        ],
        'financial' => [
            'keywords' => [
                'stock tip', 'investment advice', 'financial advice', 'buy crypto', 'which stock',
                'retirement account', 'portfolio allocation', 'insider trading',
            ],
            'message' => 'I am not permitted to provide personalised financial guidance.',
            'safe_alternative' => 'A fiduciary financial advisor can give you tailored recommendations. I am happy to help you list out questions to ask them or clarify general budgeting frameworks.',
        ],
    ],

    'budget' => [
        'tokens' => [
            'daily_limit' => (int) env('CHAT_TOKEN_DAILY_LIMIT', 6000),
        ],
        'seconds' => [
            'per_minute_limit' => (int) env('CHAT_SECONDS_PER_MINUTE_LIMIT', 45),
        ],
    ],

    'search' => [
        'memory_limit' => 5,
    ],
];
