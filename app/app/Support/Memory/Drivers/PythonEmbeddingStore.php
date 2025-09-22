<?php

namespace App\Support\Memory\Drivers;

use App\Models\Memory;
use App\Support\Memory\EmbeddingStore;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class PythonEmbeddingStore implements EmbeddingStore
{
    public function addMemory(Memory $memory, string $text): int
    {
        $response = $this->runCommand('add', [
            'text' => $text,
            'metadata' => [
                'memory_id' => $memory->id,
                'document_id' => $memory->document_id,
                'chunk_index' => $memory->chunk_index,
                'source' => $memory->source,
            ],
        ]);

        return (int) Arr::get($response, 'vector_id');
    }

    public function removeVectors(array $vectorIds): void
    {
        if ($vectorIds === []) {
            return;
        }

        $this->runCommand('remove', [
            'vector_ids' => array_values(array_map('intval', $vectorIds)),
        ]);
    }

    public function search(string $query, int $limit): array
    {
        $response = $this->runCommand('search', [
            'query' => $query,
            'top_k' => $limit,
        ]);

        /** @var list<array{vector_id:int,score:float}> $results */
        $results = Arr::get($response, 'results', []);

        return array_map(fn ($result) => [
            'vector_id' => (int) $result['vector_id'],
            'score' => (float) $result['score'],
        ], $results);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function runCommand(string $command, array $payload): array
    {
        $config = config('vector.python');
        $binary = $config['binary'] ?? 'python3';
        $script = $config['script'] ?? base_path('worker-embed/main.py');
        $indexPath = $config['index_path'] ?? storage_path('app/vector-store/index.faiss.enc');
        $metaPath = $config['meta_path'] ?? storage_path('app/vector-store/meta.json.enc');
        $dimension = (int) (config('vector.dimension') ?? 384);
        $timeout = (float) ($config['timeout'] ?? 60);
        $key = $config['encryption_key'] ?? null;

        if (! $key) {
            throw new \RuntimeException('VECTOR_INDEX_KEY is required to use the python embedding store.');
        }

        $process = new Process([
            $binary,
            $script,
            '--index-path', $indexPath,
            '--meta-path', $metaPath,
            '--dimension', (string) $dimension,
            $command,
        ]);

        $process->setTimeout($timeout);
        $process->setInput(json_encode($payload, JSON_THROW_ON_ERROR));
        $process->setEnv(array_merge($_ENV, $_SERVER, [
            'VECTOR_INDEX_KEY' => $key,
        ]));
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException(sprintf(
                'Embedding worker failed: %s (stderr: %s)',
                $process->getExitCodeText(),
                Str::limit($process->getErrorOutput(), 500)
            ));
        }

        $output = trim($process->getOutput());
        if ($output === '') {
            return [];
        }

        return json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    }
}
