<?php

namespace App\Support\Chat;

use RuntimeException;

class ChatBudgetExceededException extends RuntimeException
{
    public function __construct(
        public readonly string $budgetType,
        public readonly array $snapshot
    ) {
        parent::__construct("Chat {$budgetType} budget exceeded.");
    }
}
