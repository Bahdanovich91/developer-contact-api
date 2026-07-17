<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\HealthCheckService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Health')]
final class HealthController extends AbstractController
{
    public function __construct(
        private readonly HealthCheckService $healthCheckService,
    ) {
    }

    #[Route('/api/health', name: 'api_health', methods: ['GET'])]
    #[OA\Get(
        summary: 'Service health check',
        description: 'Checks database, AI (OpenRouter) and mailer availability.',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Service is healthy or degraded',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'status', type: 'string', example: 'ok'),
                                new OA\Property(property: 'checks', type: 'object'),
                                new OA\Property(property: 'timestamp', type: 'string', format: 'date-time'),
                            ],
                            type: 'object'
                        ),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(
                response: 503,
                description: 'Service is unavailable',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'data', type: 'object'),
                    ],
                    type: 'object'
                )
            ),
        ]
    )]
    public function __invoke(): JsonResponse
    {
        $result = $this->healthCheckService->check();
        $statusCode = 'error' === $result['status']
            ? Response::HTTP_SERVICE_UNAVAILABLE
            : Response::HTTP_OK;

        return $this->json([
            'success' => 'error' !== $result['status'],
            'data' => $result,
        ], $statusCode);
    }
}
