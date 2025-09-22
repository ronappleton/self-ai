<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\Memory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Memory>
 */
class MemoryFactory extends Factory
{
    protected $model = Memory::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $chunk = $this->faker->sentences(3, true);
        $document = Document::factory()->create();

        return [
            'id' => (string) Str::uuid(),
            'document_id' => $document->id,
            'vector_id' => $this->faker->unique()->numberBetween(1, 1000),
            'chunk_index' => 0,
            'chunk_offset' => 0,
            'chunk_length' => strlen($chunk),
            'chunk_text' => $chunk,
            'source' => $document->source,
            'embedding_model' => 'hashed-self-1',
            'embedding_hash' => hash('sha256', $chunk),
            'metadata' => [
                'test' => true,
            ],
        ];
    }
}
