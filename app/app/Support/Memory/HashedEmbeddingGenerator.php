<?php

namespace App\Support\Memory;

class HashedEmbeddingGenerator
{
    /**
     * Generate a deterministic embedding vector.
     *
     * @return list<float>
     */
    public function generate(string $text, int $dimension): array
    {
        $tokens = preg_split('/[^A-Za-z0-9\']+/', mb_strtolower($text, 'UTF-8')) ?: [];
        $vector = array_fill(0, $dimension, 0.0);

        foreach ($tokens as $token) {
            if ($token === '' || $token === null) {
                continue;
            }

            $hash = crc32($token);
            if ($hash < 0) {
                $hash += 2 ** 32;
            }

            $index = (int) ($hash % $dimension);
            $magnitude = 1.0 + (mb_strlen($token, 'UTF-8') / 10.0);
            $sign = (($hash >> 31) & 1) === 1 ? -1.0 : 1.0;
            $vector[$index] += $magnitude * $sign;
        }

        $norm = $this->magnitude($vector);
        if ($norm > 0.0) {
            foreach ($vector as $i => $value) {
                $vector[$i] = $value / $norm;
            }
        }

        return $vector;
    }

    /**
     * Calculate the magnitude of the vector.
     */
    private function magnitude(array $vector): float
    {
        $sum = 0.0;
        foreach ($vector as $value) {
            $sum += $value * $value;
        }

        return sqrt($sum);
    }

    /**
     * Calculate cosine similarity between two vectors.
     */
    public function similarity(array $a, array $b): float
    {
        $sum = 0.0;
        $count = min(count($a), count($b));
        for ($i = 0; $i < $count; $i++) {
            $sum += $a[$i] * $b[$i];
        }

        return $sum;
    }
}
