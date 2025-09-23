<?php

namespace App\Support\Audio;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class TtsWorkerCredentials
{
    public function __construct(private readonly Filesystem $filesystem)
    {
    }

    /**
     * Retrieve the active worker key, generating one if none exists.
     */
    public function activeKey(): string
    {
        $store = $this->read();
        $active = $store['active'] ?? null;
        $key = is_array($active) ? ($active['key'] ?? null) : null;

        if (is_string($key) && $key !== '') {
            return $key;
        }

        return $this->rotate('initialised');
    }

    /**
     * Rotate the active worker key and archive the previous value.
     */
    public function rotate(string $reason): string
    {
        $store = $this->read();
        $timestamp = now()->toIso8601String();

        $reason = trim($reason) === '' ? 'unspecified' : trim($reason);
        $revoked = [];

        if (isset($store['revoked']) && is_array($store['revoked'])) {
            $revoked = $store['revoked'];
        }

        $active = $store['active'] ?? null;
        if (is_array($active) && isset($active['key']) && is_string($active['key']) && $active['key'] !== '') {
            $revoked[] = [
                'key' => $active['key'],
                'revoked_at' => $timestamp,
                'reason' => $reason,
            ];
        }

        $newKey = Str::random(40);

        $store['active'] = [
            'key' => $newKey,
            'created_at' => $timestamp,
            'reason' => $reason,
        ];
        $store['revoked'] = $revoked;

        $this->write($store);

        return $newKey;
    }

    /**
     * Absolute filesystem path to the credential store.
     */
    public function filePath(): string
    {
        $configured = config('audio.tts.worker_credentials.path', 'system/tts-worker-credentials.json');

        if ($this->isAbsolutePath($configured)) {
            return $configured;
        }

        return storage_path('app/'.ltrim($configured, '/'));
    }

    /**
     * @return array<string, mixed>
     */
    private function read(): array
    {
        $path = $this->filePath();

        if (! $this->filesystem->exists($path)) {
            return [];
        }

        $contents = $this->filesystem->get($path);

        if ($contents === '' || $contents === false) {
            return [];
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function write(array $data): void
    {
        $path = $this->filePath();
        $directory = dirname($path);
        $this->filesystem->ensureDirectoryExists($directory);

        $encoded = json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);

        file_put_contents($path, $encoded, LOCK_EX);
    }

    private function isAbsolutePath(string $path): bool
    {
        if (Str::startsWith($path, ['/', '\\'])) {
            return true;
        }

        return (bool) preg_match('/^[A-Za-z]:[\\\\\/]/', $path);
    }
}
