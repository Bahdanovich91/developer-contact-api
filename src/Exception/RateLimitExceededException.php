<?php

declare(strict_types=1);

namespace App\Exception;

class RateLimitExceededException extends \RuntimeException
{
    public function __construct(
        string $message = 'Too many requests. Please try again later.',
        public readonly int $retryAfter = 60,
    ) {
        parent::__construct($message, 429);
    }
}
