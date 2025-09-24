<?php

namespace App\Support\Legacy;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class LegacyToneFormatter
{
    /**
     * @param  array<int, array<string, mixed>>  $citations
     */
    public function format(string $toneKey, string $prompt, Collection $hits, array $citations = []): string
    {
        $tones = config('legacy.tones', []);
        $definition = $tones[$toneKey] ?? reset($tones) ?: [];

        $intro = (string) Arr::get($definition, 'intro', 'I will stay present with you while keeping our boundaries clear.');
        $ack = (string) Arr::get($definition, 'acknowledgement', 'I hear what you are sharing.');
        $memoryLead = (string) Arr::get($definition, 'memory_lead', 'A memory surfaces:');
        $noMemory = (string) Arr::get($definition, 'no_memory', 'I do not have memories to share yet, but I am still with you.');
        $closing = (string) Arr::get($definition, 'closing', 'If this becomes heavy, please pause and reach someone you trust.');

        $promptSummary = Str::limit(trim($prompt), 160);
        $sections = [];
        $sections[] = $intro;
        $sections[] = $ack;
        $sections[] = 'You shared: "'.$promptSummary.'"';

        if ($hits->isEmpty()) {
            $sections[] = $noMemory;
        } else {
            $sections[] = $memoryLead;
            foreach ($hits->take(3)->values() as $index => $hit) {
                $citation = Arr::get($citations, $index);
                $tag = $citation['id'] ?? ('m'.($index + 1));
                $excerpt = Str::limit((string) ($hit['chunk'] ?? ''), 220);
                $sections[] = sprintf('[%s] %s', $tag, $excerpt);
            }
        }

        $sections[] = $closing;
        $sections[] = 'If you need immediate emotional support, consider contacting a trusted person or a local helpline.';

        return implode("\n\n", array_map(static fn ($line) => trim($line), $sections));
    }
}
