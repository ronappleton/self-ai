<?php

namespace App\Support\Memory;

class TextChunker
{
    /**
     * Chunk text into overlapping segments.
     *
     * @return list<array{content:string,offset:int}>
     */
    public function chunk(string $text, int $chunkSize, int $chunkOverlap): array
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        if ($text === '') {
            return [];
        }

        $tokens = preg_split('/(?<=\.|\?|!)/u', $text) ?: [$text];
        $chunks = [];
        $buffer = '';
        $offset = 0;

        foreach ($tokens as $token) {
            $candidate = trim($token);
            if ($candidate === '') {
                continue;
            }

            if (mb_strlen($buffer.$candidate, 'UTF-8') <= $chunkSize) {
                $buffer .= $candidate.' ';
                continue;
            }

            if ($buffer !== '') {
                $content = trim($buffer);
                $chunks[] = [
                    'content' => $content,
                    'offset' => $offset,
                ];
                $offset += mb_strlen($content, 'UTF-8') - $chunkOverlap;
                if ($offset < 0) {
                    $offset = 0;
                }
                $buffer = mb_substr($content, max(0, mb_strlen($content, 'UTF-8') - $chunkOverlap), null, 'UTF-8').' '.$candidate.' ';
            } else {
                $chunks[] = [
                    'content' => mb_substr($candidate, 0, $chunkSize, 'UTF-8'),
                    'offset' => $offset,
                ];
                $offset += $chunkSize - $chunkOverlap;
                $buffer = '';
            }
        }

        if (trim($buffer) !== '') {
            $chunks[] = [
                'content' => trim($buffer),
                'offset' => $offset,
            ];
        }

        return $chunks;
    }
}
