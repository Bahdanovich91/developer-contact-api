<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Repository\ContactRepository;
use App\Service\MetricsService;
use PHPUnit\Framework\TestCase;

final class MetricsServiceTest extends TestCase
{
    public function testGetMetricsReturnsRepositoryData(): void
    {
        $expected = [
            'total' => 10,
            'by_sentiment' => ['positive' => 5, 'neutral' => 5],
            'by_category' => ['support' => 7, 'other' => 3],
            'last_24h' => 2,
            'last_7d' => 8,
        ];

        $repository = $this->createMock(ContactRepository::class);
        $repository->expects(self::once())
            ->method('getMetrics')
            ->willReturn($expected);

        $service = new MetricsService($repository);

        self::assertSame($expected, $service->getMetrics());
    }
}
