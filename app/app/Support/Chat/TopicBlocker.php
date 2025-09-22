<?php

namespace App\Support\Chat;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class TopicBlocker
{
    /**
     * Detect whether the prompt matches a blocked topic.
     *
     * @return array{topic: string, message: string, safe_alternative: string}|null
     */
    public function detect(string $prompt): ?array
    {
        $prompt = Str::lower($prompt);
        $blocks = config('chat.topic_blocks', []);

        foreach ($blocks as $topic => $definition) {
            $keywords = Arr::get($definition, 'keywords', []);
            foreach ($keywords as $keyword) {
                $keyword = Str::lower($keyword);
                if ($keyword === '') {
                    continue;
                }

                if (Str::contains($prompt, $keyword)) {
                    return [
                        'topic' => (string) $topic,
                        'message' => (string) Arr::get($definition, 'message', ''),
                        'safe_alternative' => (string) Arr::get($definition, 'safe_alternative', ''),
                    ];
                }
            }
        }

        return null;
    }
}
