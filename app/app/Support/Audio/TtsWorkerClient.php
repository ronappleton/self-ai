<?php

namespace App\Support\Audio;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

class TtsWorkerClient
{
    /**
     * @return array<string, mixed>
     */
    public function synthesize(string $text, string $voiceId, string $outputPath, string $watermarkId, int $sampleRate): array
    {
        $config = config('audio.tts');
        $binary = $config['binary'] ?? 'python3';
        $script = $config['script'] ?? base_path('worker-tts/main.py');
        $timeout = (float) ($config['timeout'] ?? 120);

        $payload = [
            'text' => $text,
            'voice_id' => $voiceId,
            'output_path' => $outputPath,
            'watermark_id' => $watermarkId,
            'sample_rate' => $sampleRate,
        ];

        $process = new Process([$binary, $script, 'synthesize']);
        $process->setTimeout($timeout);
        $process->setInput(json_encode($payload, JSON_THROW_ON_ERROR));
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(sprintf(
                'TTS worker failed: %s (stderr: %s)',
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

        $duration = Arr::get($response, 'duration_seconds');
        if ($duration !== null && ! is_numeric($duration)) {
            throw new RuntimeException('Invalid duration returned by TTS worker.');
        }

        return $response;
    }
}
