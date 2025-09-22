<?php

namespace App\Support\Audio;

use RuntimeException;

class VoiceImpersonationRejectedException extends RuntimeException
{
    public function __construct(
        public readonly string $keyword,
        public readonly string $messageText,
        public readonly string $alternative
    ) {
        parent::__construct($messageText);
    }
}
