<?php

namespace Database\Factories;

use App\Models\Build;
use App\Models\RfcProposal;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Build>
 */
class BuildFactory extends Factory
{
    protected $model = Build::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'rfc_id' => RfcProposal::factory(),
            'status' => 'passed',
            'diff_disk' => 'minio',
            'diff_path' => 'builds/diff.json',
            'test_report_disk' => 'minio',
            'test_report_path' => 'builds/tests.json',
            'artefacts_disk' => 'minio',
            'artefacts_path' => 'builds/artefacts.json',
            'metadata' => [
                'summary' => 'Initial build factory state',
                'manifest_disk' => 'minio',
                'manifest_path' => 'builds/manifest.json',
            ],
        ];
    }
}
