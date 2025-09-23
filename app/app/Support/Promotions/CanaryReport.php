<?php

namespace App\Support\Promotions;

class CanaryReport
{
    /**
     * @param  array<string, array<string, mixed>>  $checks
     */
    public function __construct(public readonly string $status, public readonly array $checks)
    {
    }

    public function passed(): bool
    {
        return $this->status === 'passed';
    }
}
