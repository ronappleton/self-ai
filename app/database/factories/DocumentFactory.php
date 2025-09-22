<?php

namespace Database\Factories;

use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $content = $this->faker->paragraphs(2, true);

        return [
            'id' => (string) Str::uuid(),
            'source' => $this->faker->domainName(),
            'type' => 'text',
            'status' => 'pending',
            'sha256' => hash('sha256', $content),
            'storage_disk' => 'minio',
            'storage_path' => 'ingest/'.$this->faker->uuid.'.txt',
            'original_filename' => null,
            'mime_type' => 'text/plain',
            'metadata' => ['faker' => true],
            'tags' => ['sample'],
            'retention_class' => 'standard',
            'consent_scope' => 'internal',
            'pii_scrubbed' => true,
            'sanitized_content' => $content,
        ];
    }
}
