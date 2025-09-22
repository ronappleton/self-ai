<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Voice;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Voice>
 */
class VoiceFactory extends Factory
{
    protected $model = Voice::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'voice_id' => 'owner',
            'status' => 'active',
            'storage_disk' => 'minio',
            'dataset_path' => 'voice/owner/'.$this->faker->uuid.'/dataset.zip',
            'dataset_sha256' => hash('sha256', $this->faker->text()),
            'sample_count' => $this->faker->numberBetween(10, 40),
            'script_version' => 'v'.$this->faker->numberBetween(1, 3),
            'consent_scope' => 'owner-voice',
            'metadata' => [
                'run_id' => (string) Str::uuid(),
                'script_acknowledged' => true,
            ],
            'enrolled_at' => now(),
            'enrolled_by' => User::factory(),
        ];
    }
}
