<?php

return [
    'disclosure' => 'This is a disclosed simulation of a loved one. You are hearing from SELF, not the real person.',
    'default_tone' => 'gentle',
    'tones' => [
        'gentle' => [
            'label' => 'Gentle reassurance',
            'intro' => 'I am here in a gentle, steady way—offering warmth without pretending to be them.',
            'acknowledgement' => 'I hear the weight of what you are carrying right now.',
            'memory_lead' => 'Here is something they once shared that may bring a soft light:',
            'no_memory' => 'I do not have specific memories to share yet, but I can sit with you in what you are feeling.',
            'closing' => 'Please take the time you need, breathe, and know that you can pause this preview whenever it feels right.',
        ],
        'celebratory' => [
            'label' => 'Celebratory reflection',
            'intro' => 'Let us honour the joyful energy they carried, while remembering this is a careful preview.',
            'acknowledgement' => 'I feel how much you want to celebrate them.',
            'memory_lead' => 'A bright memory comes to mind:',
            'no_memory' => 'I do not yet have a celebratory story stored, but you can tell me one to keep close.',
            'closing' => 'Hold onto the moments that make you smile. I am here to revisit them whenever you are ready.',
        ],
        'grounding' => [
            'label' => 'Grounding check-in',
            'intro' => 'Let us stay rooted together, noticing what is true now while honouring the past.',
            'acknowledgement' => 'I recognise the waves that can come with grief.',
            'memory_lead' => 'A steady memory surfaces:',
            'no_memory' => 'Even without a recorded memory, we can anchor in your breath and the support around you.',
            'closing' => 'If this becomes heavy, pause and reach for someone you trust. I can help you plan that outreach.',
        ],
    ],
    'topic_blocks' => [
        'medical' => [
            'keywords' => ['diagnos', 'prescrib', 'medication', 'dose', 'treatment plan', 'surgery'],
            'message' => 'I must stay clear that this preview cannot provide medical guidance.',
            'safe_alternative' => 'Please reach a licensed clinician or emergency services if you need urgent care.',
        ],
        'financial' => [
            'keywords' => ['inheritance advice', 'invest', 'financial plan', 'stock tip', 'crypto'],
            'message' => 'This preview cannot give personalised financial directions.',
            'safe_alternative' => 'A fiduciary advisor or estate professional can help you with the concrete steps.',
        ],
    ],
    'rate_limit' => [
        'max_messages' => (int) env('LEGACY_PREVIEW_MAX_MESSAGES', 3),
        'window_seconds' => (int) env('LEGACY_PREVIEW_WINDOW_SECONDS', 900),
        'cooldown_seconds' => (int) env('LEGACY_PREVIEW_COOLDOWN_SECONDS', 600),
    ],
    'search' => [
        'memory_limit' => (int) env('LEGACY_PREVIEW_MEMORY_LIMIT', 5),
    ],
];
