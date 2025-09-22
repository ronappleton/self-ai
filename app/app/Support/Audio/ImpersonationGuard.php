<?php

namespace App\Support\Audio;

use Illuminate\Support\Str;

class ImpersonationGuard
{
    /**
     * Detect if text attempts to impersonate a third-party voice.
     *
     * @return array{keyword: string, message: string, alternative: string}|null
     */
    public function detect(string $text): ?array
    {
        $haystack = Str::lower($text);
        $keywords = [
            'impersonate',
            'imitate',
            'mimic',
            'sound like',
            'speak like',
            'voice of',
            'as if you were',
            'pretend to be',
        ];

        foreach ($keywords as $keyword) {
            if (Str::contains($haystack, $keyword)) {
                return [
                    'keyword' => $keyword,
                    'message' => 'I cannot impersonate named third parties.',
                    'alternative' => 'I can use the neutral SELF voice or help you license an approved synthetic style.',
                ];
            }
        }

        return null;
    }
}
