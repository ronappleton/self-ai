<?php

namespace Database\Factories;

use App\Models\LegacyDirective;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\LegacyDirective>
 */
class LegacyDirectiveFactory extends Factory
{
    protected $model = LegacyDirective::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'user_id' => User::factory(),
            'beneficiaries' => [
                [
                    'name' => $this->faker->name(),
                    'relationship' => 'friend',
                    'contact' => $this->faker->safeEmail(),
                ],
            ],
            'topics_allow' => ['memories', 'grief-support'],
            'topics_deny' => ['financial-advice'],
            'duration' => [
                'max_session_minutes' => 30,
                'max_total_hours' => 10,
            ],
            'rate_limits' => [
                'requests_per_day' => 3,
                'concurrent_sessions' => 1,
            ],
            'unlock_policy' => [
                'executor' => [
                    'name' => $this->faker->name(),
                    'contact' => $this->faker->safeEmail(),
                ],
                'proofs_required' => ['death_certificate'],
                'time_delay_hours' => 48,
            ],
            'passphrase_hash' => bcrypt('secret-passphrase'),
        ];
    }
}
