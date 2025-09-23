<?php

namespace App\Support\Promotions;

use App\Support\Promotions\Exceptions\InvalidPromotionSignatureException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class PromotionSignatureVerifier
{
    /**
     * @var array<string, string>
     */
    private array $keys;

    private int $maxSkew;

    private int $maxTtl;

    /**
     * @param  array{keys?: array<string, string>, max_skew?: int, max_ttl?: int}|null  $config
     */
    public function __construct(?array $config = null)
    {
        $config ??= config('promotion.verifier', []);
        $this->keys = (array) Arr::get($config, 'keys', []);
        $this->maxSkew = (int) Arr::get($config, 'max_skew', 120);
        $this->maxTtl = max(1, (int) Arr::get($config, 'max_ttl', 900));
    }

    /**
     * @param  array{verifier_id?: string, signature?: string, nonce?: string, requested_at?: string, expires_at?: string}  $payload
     */
    public function verify(string $buildId, array $payload): PromotionSignature
    {
        $verifierId = Str::of((string) ($payload['verifier_id'] ?? ''))->trim()->toString();
        $signature = (string) ($payload['signature'] ?? '');
        $nonce = Str::of((string) ($payload['nonce'] ?? ''))->trim()->toString();
        $requestedAtRaw = (string) ($payload['requested_at'] ?? '');
        $expiresAtRaw = (string) ($payload['expires_at'] ?? '');

        if ($verifierId === '') {
            throw new InvalidPromotionSignatureException('Verifier identifier missing.');
        }

        if ($nonce === '') {
            throw new InvalidPromotionSignatureException('Nonce must be provided.');
        }

        $key = $this->keys[$verifierId] ?? null;
        if (! is_string($key) || $key === '') {
            throw new InvalidPromotionSignatureException('Verifier key not recognised.');
        }

        try {
            $requestedAt = CarbonImmutable::parse($requestedAtRaw);
            $expiresAt = CarbonImmutable::parse($expiresAtRaw);
        } catch (\Throwable $exception) {
            throw new InvalidPromotionSignatureException('Unable to parse timestamp fields.', 0, $exception);
        }

        $now = CarbonImmutable::now();

        if ($requestedAt->greaterThan($now->addSeconds($this->maxSkew))) {
            throw new InvalidPromotionSignatureException('Request timestamp is too far in the future.');
        }

        if ($requestedAt->lessThan($now->subSeconds($this->maxTtl))) {
            throw new InvalidPromotionSignatureException('Request timestamp is too old.');
        }

        if ($expiresAt->lessThanOrEqualTo($requestedAt)) {
            throw new InvalidPromotionSignatureException('Expiry must be after the request timestamp.');
        }

        if ($expiresAt->lessThan($now)) {
            throw new InvalidPromotionSignatureException('Signature has expired.');
        }

        if ($expiresAt->diffInSeconds($requestedAt) > $this->maxTtl) {
            throw new InvalidPromotionSignatureException('Signature validity window is too large.');
        }

        $canonical = $this->canonicalPayload([
            'build_id' => $buildId,
            'expires_at' => $expiresAt->toIso8601String(),
            'nonce' => (string) $nonce,
            'requested_at' => $requestedAt->toIso8601String(),
            'verifier_id' => $verifierId,
        ]);

        $expected = hash_hmac('sha256', $canonical, $key, true);
        $provided = base64_decode($signature, true);

        if (! is_string($provided)) {
            throw new InvalidPromotionSignatureException('Signature is not valid base64.');
        }

        if (! hash_equals($expected, $provided)) {
            throw new InvalidPromotionSignatureException('Signature verification failed.');
        }

        return new PromotionSignature($verifierId, base64_encode($expected), (string) $nonce, $requestedAt, $expiresAt);
    }

    /**
     * @param  array<string, string>  $payload
     */
    private function canonicalPayload(array $payload): string
    {
        ksort($payload);

        return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param  array{build_id: string, verifier_id: string, nonce: string, requested_at: string, expires_at: string}  $payload
     */
    public static function signPayload(string $key, array $payload): string
    {
        $requestedAt = CarbonImmutable::parse($payload['requested_at']);
        $expiresAt = CarbonImmutable::parse($payload['expires_at']);

        $canonical = self::canonicalise([
            'build_id' => $payload['build_id'],
            'expires_at' => $expiresAt->toIso8601String(),
            'nonce' => $payload['nonce'],
            'requested_at' => $requestedAt->toIso8601String(),
            'verifier_id' => $payload['verifier_id'],
        ]);

        return base64_encode(hash_hmac('sha256', $canonical, $key, true));
    }

    /**
     * @param  array<string, string>  $payload
     */
    private static function canonicalise(array $payload): string
    {
        ksort($payload);

        return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }
}
