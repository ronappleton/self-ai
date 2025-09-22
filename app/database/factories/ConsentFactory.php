<?php

namespace Database\Factories;

use App\Models\Consent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Consent>
 */
class ConsentFactory extends Factory
{
    protected $model = Consent::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source' => $this->faker->domainName(),
            'scope' => 'internal',
            'status' => 'pending',
            'notes' => null,
        ];
    }
}
