<?php

namespace App\Support\Ingestion;

class PiiScrubber
{
    /**
     * Scrub basic PII patterns from the given text.
     */
    public function scrub(string $text): string
    {
        $scrubbed = $this->scrubEmails($text);
        $scrubbed = $this->scrubPhoneNumbers($scrubbed);

        return $scrubbed;
    }

    private function scrubEmails(string $text): string
    {
        $pattern = '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i';

        return (string) preg_replace($pattern, '[REDACTED:EMAIL]', $text);
    }

    private function scrubPhoneNumbers(string $text): string
    {
        $pattern = '/\b(?:\+?\d{1,3}[\s.-]?)?(?:\(\d{3}\)|\d{3})[\s.-]?\d{3}[\s.-]?\d{4}\b/';

        return (string) preg_replace($pattern, '[REDACTED:PHONE]', $text);
    }
}
