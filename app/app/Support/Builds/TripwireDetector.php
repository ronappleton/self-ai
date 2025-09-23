<?php

namespace App\Support\Builds;

use Illuminate\Support\Str;

class TripwireDetector
{
    /**
     * @param  array<int, array<string, mixed>|string>  $files
     * @return array{category: string, path: string}|null
     */
    public function detect(array $files): ?array
    {
        $patterns = config('builds.tripwires', []);

        foreach ($files as $entry) {
            $path = is_array($entry) ? ($entry['path'] ?? '') : (string) $entry;
            if ($path === '') {
                continue;
            }

            $normalised = Str::of($path)->replace('\\', '/')->ltrim('/')->lower();

            foreach ($patterns as $category => $list) {
                foreach ((array) $list as $needle) {
                    $needleNormalised = Str::of($needle)->replace('\\', '/')->ltrim('/')->lower();
                    if ($needleNormalised === '') {
                        continue;
                    }

                    if ($this->matches($normalised, $needleNormalised)) {
                        return [
                            'category' => (string) $category,
                            'path' => $path,
                        ];
                    }
                }
            }
        }

        return null;
    }

    private function matches(string $path, string $needle): bool
    {
        if (str_ends_with($needle, '/')) {
            return str_starts_with($path, rtrim($needle, '/'));
        }

        if (str_contains($needle, '*')) {
            $pattern = preg_quote($needle, '/');
            $pattern = str_replace('\*', '.*', $pattern);

            return (bool) preg_match('/^' . $pattern . '$/i', $path);
        }

        return $path === $needle;
    }
}

