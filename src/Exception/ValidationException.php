<?php

declare(strict_types=1);

namespace App\Exception;

class ValidationException extends \RuntimeException
{
    /**
     * @param list<array{field: string, message: string}> $errors
     */
    public function __construct(
        string $message = 'Validation failed',
        public readonly array $errors = [],
    ) {
        parent::__construct($message, 422);
    }
}
