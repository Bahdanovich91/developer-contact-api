<?php

declare(strict_types=1);

namespace App\Dto;

final readonly class AiAnalysisResult
{
    public function __construct(
        public string $sentiment,
        public string $category,
        public string $autoReply,
        public bool $usedFallback = false,
    ) {
    }

    public static function fallback(string $reply): self
    {
        return new self(
            sentiment: 'unknown',
            category: 'other',
            autoReply: $reply,
            usedFallback: true,
        );
    }
}
