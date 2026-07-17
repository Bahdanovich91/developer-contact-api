<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\MetricsService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Metrics')]
final class MetricsController extends AbstractController
{
    public function __construct(
        private readonly MetricsService $metricsService,
    ) {
    }

    #[Route('/api/metrics', name: 'api_metrics', methods: ['GET'])]
    #[OA\Get(
        summary: 'Contact metrics',
        description: 'Returns aggregated statistics about contact submissions stored in MySQL.',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Metrics payload',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'total', type: 'integer', example: 42),
                                new OA\Property(property: 'by_sentiment', type: 'object'),
                                new OA\Property(property: 'by_category', type: 'object'),
                                new OA\Property(property: 'last_24h', type: 'integer', example: 3),
                                new OA\Property(property: 'last_7d', type: 'integer', example: 12),
                            ],
                            type: 'object'
                        ),
                    ],
                    type: 'object'
                )
            ),
        ]
    )]
    public function __invoke(): JsonResponse
    {
        return $this->json([
            'success' => true,
            'data' => $this->metricsService->getMetrics(),
        ]);
    }
}
