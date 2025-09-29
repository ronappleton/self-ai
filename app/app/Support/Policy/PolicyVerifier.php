<?php

namespace App\Support\Policy;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

class PolicyVerifier
{
    private string $policyPath;

    private string $signaturePath;

    private string $publicKeyPath;

    public function __construct()
    {
        $this->policyPath = base_path(config('policy.immutable_path'));
        $this->signaturePath = base_path(config('policy.signature_path'));
        $this->publicKeyPath = base_path(config('policy.public_key_path'));
    }

    /**
     * @throws RuntimeException
     */
    public function verify(): array
    {
        $cacheKey = 'policy:immutable:verification';
        $store = config('policy.cache_store', config('cache.default', 'file'));

        return Cache::store($store)->rememberForever($cacheKey, function (): array {
            $policyContents = $this->readFile($this->policyPath);
            $signatureContents = $this->readFile($this->signaturePath);
            $publicKeyContents = $this->readFile($this->publicKeyPath);

            $signature = base64_decode(trim($signatureContents), true);

            if ($signature === false) {
                throw new RuntimeException('Immutable policy signature is not valid base64.');
            }

            $publicKey = openssl_pkey_get_public($publicKeyContents);

            if ($publicKey === false) {
                throw new RuntimeException('Unable to load immutable policy public key.');
            }

            try {
                $result = openssl_verify($policyContents, $signature, $publicKey, OPENSSL_ALGO_SHA256);
            } finally {
                openssl_free_key($publicKey);
            }

            if ($result !== 1) {
                throw new RuntimeException('Immutable policy signature verification failed.');
            }

            $parsed = Yaml::parse($policyContents);

            if (! is_array($parsed) || empty($parsed['id']) || empty($parsed['version'])) {
                throw new RuntimeException('Immutable policy is missing required metadata.');
            }

            $hash = hash('sha256', $policyContents);

            return [
                'id' => $parsed['id'],
                'version' => $parsed['version'],
                'issued_at' => $parsed['issued_at'] ?? null,
                'hash' => $hash,
            ];
        });
    }

    private function readFile(string $path): string
    {
        if (! file_exists($path)) {
            throw new FileNotFoundException("Immutable policy file missing: {$path}");
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Unable to read immutable policy file: {$path}");
        }

        return $contents;
    }
}
