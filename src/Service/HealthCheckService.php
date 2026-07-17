<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

final class HealthCheckService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly OpenRouterAiService $openRouterAiService,
        private readonly ContactMailerService $contactMailerService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return array{
     *     status: string,
     *     checks: array<string, array{status: string, message: string}>,
     *     timestamp: string
     * }
     */
    public function check(): array
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'ai' => $this->checkAi(),
            'mailer' => $this->checkMailer(),
        ];

        $statuses = array_column($checks, 'status');
        $overall = 'ok';

        if (\in_array('error', $statuses, true)) {
            $overall = 'error';
        } elseif (\in_array('degraded', $statuses, true)) {
            $overall = 'degraded';
        }

        return [
            'status' => $overall,
            'checks' => $checks,
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return array{status: string, message: string}
     */
    private function checkDatabase(): array
    {
        try {
            $this->connection->executeQuery('SELECT 1');

            return [
                'status' => 'ok',
                'message' => 'Database connection is healthy',
            ];
        } catch (\Throwable $exception) {
            $this->logger->error('Health check: database failed', [
                'error' => $exception->getMessage(),
            ]);

            return [
                'status' => 'error',
                'message' => 'Database is unavailable',
            ];
        }
    }

    /**
     * @return array{status: string, message: string}
     */
    private function checkAi(): array
    {
        try {
            if ($this->openRouterAiService->isAvailable()) {
                return [
                    'status' => 'ok',
                    'message' => 'OpenRouter API is reachable',
                ];
            }

            return [
                'status' => 'degraded',
                'message' => 'OpenRouter API is unavailable; fallback will be used',
            ];
        } catch (\Throwable $exception) {
            $this->logger->error('Health check: AI failed', [
                'error' => $exception->getMessage(),
            ]);

            return [
                'status' => 'degraded',
                'message' => 'OpenRouter API check failed; fallback will be used',
            ];
        }
    }

    /**
     * @return array{status: string, message: string}
     */
    private function checkMailer(): array
    {
        if ($this->contactMailerService->isAvailable()) {
            return [
                'status' => 'ok',
                'message' => 'Mailer configuration looks valid',
            ];
        }

        return [
            'status' => 'degraded',
            'message' => 'Mailer configuration is incomplete',
        ];
    }
}
