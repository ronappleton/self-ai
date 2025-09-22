<?php

namespace App\Support\Audio;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

class AsrWorkerClient
{
    /**
     * @return array<string, mixed>
     */
    public function transcribe(string $audioPath): array
    {
        if ($audioPath === '') {
            throw new RuntimeException('Audio path is required for transcription.');
        }

        $config = config('audio.asr');
        $binary = $config['binary'] ?? 'python3';
        $script = $config['script'] ?? base_path('worker-asr/main.py');
        $timeout = (float) ($config['timeout'] ?? 120);

        $payload = [
            'audio_path' => $audioPath,
        ];

        $process = new Process([$binary, $script, 'transcribe']);
        $process->setTimeout($timeout);
        $process->setInput(json_encode($payload, JSON_THROW_ON_ERROR));
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(sprintf(
                'ASR worker failed: %s (stderr: %s)',
                $process->getExitCodeText(),
                Str::limit($process->getErrorOutput(), 500)
            ));
        }

        $output = trim($process->getOutput());
        if ($output === '') {
            return [];
        }

        /** @var array<string, mixed> $response */
        $response = json_decode($output, true, 512, JSON_THROW_ON_ERROR);

        $transcript = Arr::get($response, 'transcript');
        if (! is_string($transcript)) {
            throw new RuntimeException('ASR worker did not return a transcript.');
        }

        return $response;
    }
}
