<?php

namespace Tests\Unit;

use App\Support\Policy\PolicyVerifier;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

class PolicyVerifierTest extends TestCase
{
    public function test_verify_throws_when_signature_invalid(): void
    {
        $originalConfig = config('policy');
        $cacheStore = $originalConfig['cache_store'] ?? config('cache.default', 'array');

        $invalidSignaturePath = base_path('storage/framework/testing/policy/immutable-policy.sig');
        File::ensureDirectoryExists(dirname($invalidSignaturePath));
        File::put($invalidSignaturePath, base64_encode('totally-invalid-signature'));

        config([
            'policy.signature_path' => 'storage/framework/testing/policy/immutable-policy.sig',
        ]);

        Cache::store($cacheStore)->forget('policy:immutable:verification');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Immutable policy signature verification failed.');

        try {
            app(PolicyVerifier::class)->verify();
        } finally {
            config([
                'policy.signature_path' => $originalConfig['signature_path'] ?? 'policy/immutable-policy.sig',
            ]);

            Cache::store($cacheStore)->forget('policy:immutable:verification');
            File::delete($invalidSignaturePath);
        }
    }
}
