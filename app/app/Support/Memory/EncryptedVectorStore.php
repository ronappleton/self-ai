<?php

namespace App\Support\Memory;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class EncryptedVectorStore
{
    private string $key;

    public function __construct(?string $encryptionKey = null)
    {
        $this->key = $this->normalizeKey($encryptionKey ?? (string) config('vector.encryption_key'));
    }

    /**
     * Store an embedding payload and return the storage path.
     *
     * @param array<string,float> $embedding
     */
    public function store(string $documentId, int $chunkIndex, array $embedding): string
    {
        $payload = json_encode($embedding, JSON_THROW_ON_ERROR);
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($payload, 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, $iv, $tag);

        if ($ciphertext === false) {
            throw new RuntimeException('Unable to encrypt embedding payload.');
        }

        $record = json_encode([
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
            'ciphertext' => base64_encode($ciphertext),
        ], JSON_THROW_ON_ERROR);

        $path = sprintf('documents/%s/chunks/%s.json', $documentId, $chunkIndex);
        $disk = $this->filesystem();
        $result = $disk->put($path, $record);

        if ($result === false) {
            throw new RuntimeException('Unable to write encrypted vector payload.');
        }

        return $path;
    }

    /**
     * Retrieve an embedding payload from storage.
     *
     * @return array<string,float>
     */
    public function retrieve(string $path): array
    {
        $disk = $this->filesystem();

        if (! $disk->exists($path)) {
            throw new RuntimeException("Vector payload missing: {$path}");
        }

        $contents = $disk->get($path);
        $record = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        $ciphertext = base64_decode((string) ($record['ciphertext'] ?? ''), true);
        $iv = base64_decode((string) ($record['iv'] ?? ''), true);
        $tag = base64_decode((string) ($record['tag'] ?? ''), true);

        if ($ciphertext === false || $iv === false || $tag === false) {
            throw new RuntimeException('Stored vector payload is corrupted.');
        }

        $plaintext = openssl_decrypt($ciphertext, 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, $iv, $tag);

        if ($plaintext === false) {
            throw new RuntimeException('Unable to decrypt stored vector payload.');
        }

        /** @var array<string,float> $embedding */
        $embedding = json_decode($plaintext, true, 512, JSON_THROW_ON_ERROR);

        return $embedding;
    }

    public function delete(string $path): void
    {
        $this->filesystem()->delete($path);
    }

    public function hash(string $path): string
    {
        $disk = $this->filesystem();

        if (! $disk->exists($path)) {
            throw new RuntimeException("Vector payload missing: {$path}");
        }

        return hash('sha256', $disk->get($path));
    }

    private function filesystem(): Filesystem
    {
        $diskName = (string) config('vector.disk', 'minio');

        return Storage::disk($diskName);
    }

    private function normalizeKey(string $rawKey): string
    {
        $key = trim($rawKey);

        if ($key === '') {
            throw new RuntimeException('Vector store encryption key is not configured.');
        }

        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);

            if ($decoded === false) {
                throw new RuntimeException('Vector store encryption key is not valid base64.');
            }

            $key = $decoded;
        }

        if (strlen($key) < 32) {
            $key = hash('sha256', $key, true);
        }

        return substr($key, 0, 32);
    }
}
