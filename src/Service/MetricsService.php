<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\ContactRepository;

final class MetricsService
{
    public function __construct(
        private readonly ContactRepository $contactRepository,
    ) {
    }

    /**
     * @return array{
     *     total: int,
     *     by_sentiment: array<string, int>,
     *     by_category: array<string, int>,
     *     last_24h: int,
     *     last_7d: int
     * }
     */
    public function getMetrics(): array
    {
        return $this->contactRepository->getMetrics();
    }
}
