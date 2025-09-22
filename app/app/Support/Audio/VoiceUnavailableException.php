<?php

namespace App\Support\Audio;

use RuntimeException;

class VoiceUnavailableException extends RuntimeException
{
    public function __construct(
        public readonly string $voiceId,
        public readonly string $reason
    ) {
        parent::__construct("Voice '{$voiceId}' is unavailable ({$reason}).");
    }

    public static function forMissingVoice(string $voiceId): self
    {
        return new self($voiceId, 'not_enrolled');
    }

    public static function forDisabledVoice(string $voiceId, string $status): self
    {
        return new self($voiceId, $status);
    }
}
