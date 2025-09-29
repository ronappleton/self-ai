<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\LegacyDirective;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LegacyDirectiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_owner_can_create_directive_and_export(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');

        Sanctum::actingAs($owner, ['*']);

        $response = $this->postJson('/api/v1/legacy/directive', [
            'beneficiaries' => [
                [
                    'name' => 'Alex Executor',
                    'relationship' => 'Sibling',
                    'contact' => 'alex@example.com',
                    'notes' => 'Primary executor contact.',
                ],
            ],
            'topics' => [
                'allow' => ['memories', 'grief Support'],
                'deny' => ['financial', 'Medical'],
            ],
            'duration' => [
                'max_session_minutes' => 45,
            ],
            'rate_limits' => [
                'requests_per_day' => 4,
                'concurrent_sessions' => 1,
            ],
            'unlock_policy' => [
                'executor' => [
                    'name' => 'Alex Executor',
                    'contact' => 'alex@example.com',
                ],
                'proofs_required' => ['Death Certificate'],
                'time_delay_hours' => 72,
                'passphrase_hint' => 'Shared hiking trip location.',
            ],
            'passphrase' => 'graceful-pass-123',
        ]);

        $response->assertCreated();

        $payload = $response->json('directive');
        $this->assertSame('Alex Executor', $payload['beneficiaries'][0]['name']);
        $this->assertContains('memories', $payload['topics']['allow']);
        $this->assertContains('financial', $payload['topics']['deny']);

        $directive = LegacyDirective::query()->where('user_id', $owner->id)->firstOrFail();
        $this->assertNotNull($directive->passphrase_hash);
        $this->assertTrue(Hash::check('graceful-pass-123', $directive->passphrase_hash));
        $this->assertNotSame('graceful-pass-123', $directive->passphrase_hash);
    }

    public function test_unlock_denied_when_passphrase_incorrect_is_audited(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $directive = LegacyDirective::factory()->create([
            'user_id' => $owner->id,
            'passphrase_hash' => Hash::make('correct-horse'),
        ]);

        $operator = User::factory()->create();
        $operator->assignRole('operator');

        Sanctum::actingAs($operator, ['*']);

        $response = $this->postJson('/api/v1/legacy/directive/unlock', [
            'directive_id' => $directive->id,
            'executor_name' => 'Executor Proof',
            'passphrase' => 'wrong-passphrase',
            'proof_reference' => 'vault-link-123',
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'status' => 'denied',
            'reason' => 'invalid_passphrase',
        ]);

        $unlock = $directive->unlocks()->latest('id')->first();
        $this->assertNotNull($unlock);
        $this->assertSame('denied', $unlock->status);
        $this->assertSame('invalid_passphrase', $unlock->reason);

        $audit = AuditLog::query()
            ->where('action', 'legacy.directive.unlock.denied')
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame('legacy_directive', $audit->target);
        $this->assertSame('invalid_passphrase', $audit->context['reason']);
    }
}
