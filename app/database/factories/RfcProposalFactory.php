<?php

namespace Database\Factories;

use App\Models\RfcProposal;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RfcProposal>
 */
class RfcProposalFactory extends Factory
{
    protected $model = RfcProposal::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'title' => $this->faker->sentence(5),
            'scope' => $this->faker->paragraph(),
            'risks' => $this->faker->sentence(),
            'tests' => [
                ['name' => 'unit', 'command' => 'php artisan test'],
                ['name' => 'lint', 'command' => 'vendor/bin/pint --test'],
            ],
            'budget' => $this->faker->numberBetween(1, 10),
            'status' => 'draft',
        ];
    }
}
