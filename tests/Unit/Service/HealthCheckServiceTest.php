<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\ContactMailerService;
use App\Service\HealthCheckService;
use App\Service\OpenRouterAiService;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class HealthCheckServiceTest extends KernelTestCase
{
    protected function tearDown(): void
    {
        self::ensureKernelShutdown();

        parent::tearDown();
    }

    public function testCheckReturnsOkWhenAllDependenciesAreHealthy(): void
    {
        self::bootKernel();

        static::getContainer()->set(
            HttpClientInterface::class,
            new MockHttpClient([
                new MockResponse(
                    json_encode(['data' => []], JSON_THROW_ON_ERROR),
                    ['http_code' => 200]
                ),
            ])
        );

        $connection = $this->createStub(Connection::class);
        $result = $this->createStub(Result::class);

        $connection
            ->method('executeQuery')
            ->willReturn($result);

        $service = new HealthCheckService(
            connection: $connection,
            openRouterAiService: $this->createOpenRouterService(),
            contactMailerService: $this->createMailerService(),
            logger: new NullLogger(),
        );

        $health = $service->check();

        self::assertSame('ok', $health['status']);
        self::assertSame(
            'ok',
            $health['checks']['database']['status']
        );
        self::assertSame(
            'ok',
            $health['checks']['ai']['status']
        );
        self::assertSame(
            'ok',
            $health['checks']['mailer']['status']
        );

        self::assertArrayHasKey(
            'timestamp',
            $health
        );
    }


    public function testCheckReturnsErrorWhenDatabaseFails(): void
    {
        self::bootKernel();

        static::getContainer()->set(
            HttpClientInterface::class,
            new MockHttpClient([
                new MockResponse(
                    json_encode(['data' => []], JSON_THROW_ON_ERROR),
                    ['http_code' => 200]
                ),
            ])
        );

        $connection = $this->createStub(Connection::class);

        $connection
            ->method('executeQuery')
            ->willThrowException(
                new \RuntimeException('Connection refused')
            );

        $service = new HealthCheckService(
            connection: $connection,
            openRouterAiService: $this->createOpenRouterService(),
            contactMailerService: $this->createMailerService(),
            logger: new NullLogger(),
        );

        $health = $service->check();

        self::assertSame(
            'error',
            $health['status']
        );

        self::assertSame(
            'error',
            $health['checks']['database']['status']
        );
    }


    public function testCheckReturnsDegradedWhenAiIsUnavailable(): void
    {
        self::bootKernel();

        static::getContainer()->set(
            HttpClientInterface::class,
            new MockHttpClient([
                new MockResponse(
                    'unavailable',
                    ['http_code' => 503]
                ),
            ])
        );

        $connection = $this->createStub(Connection::class);
        $result = $this->createStub(Result::class);

        $connection
            ->method('executeQuery')
            ->willReturn($result);

        $service = new HealthCheckService(
            connection: $connection,
            openRouterAiService: $this->createOpenRouterService(),
            contactMailerService: $this->createMailerService(),
            logger: new NullLogger(),
        );

        $health = $service->check();

        self::assertSame(
            'degraded',
            $health['status']
        );

        self::assertSame(
            'degraded',
            $health['checks']['ai']['status']
        );
    }

    public function testCheckReturnsDegradedWhenMailerIsUnavailable(): void
    {
        self::bootKernel();

        static::getContainer()->set(
            HttpClientInterface::class,
            new MockHttpClient([
                new MockResponse(
                    json_encode(['data' => []], JSON_THROW_ON_ERROR),
                    ['http_code' => 200]
                ),
            ])
        );

        $connection = $this->createStub(Connection::class);
        $result = $this->createStub(Result::class);

        $connection
            ->method('executeQuery')
            ->willReturn($result);

        $service = new HealthCheckService(
            connection: $connection,
            openRouterAiService: $this->createOpenRouterService(),
            contactMailerService: $this->createMailerService('null://null'),
            logger: new NullLogger(),
        );

        $health = $service->check();

        self::assertSame('degraded', $health['status']);
        self::assertSame('degraded', $health['checks']['mailer']['status']);
    }


    private function createOpenRouterService(): OpenRouterAiService
    {
        $container = static::getContainer();

        return new OpenRouterAiService(
            $container->get(HttpClientInterface::class),
            $container->get('monolog.logger.ai'),
            'test-key',
            (string) $container->getParameter('app.openrouter.api_url'),
            (string) $container->getParameter('app.openrouter.model'),
            (int) $container->getParameter('app.openrouter.timeout'),
            (string) $container->getParameter('app.openrouter.fallback_reply'),
        );
    }


    private function createMailerService(
        string $mailerDsn = 'smtp://user:pass@sandbox.smtp.mailtrap.io:2525',
    ): ContactMailerService {
        $container = static::getContainer();

        return new ContactMailerService(
            mailer: $container->get(MailerInterface::class),
            logger: new NullLogger(),
            ownerEmail: (string) $container->getParameter('app.owner_email'),
            mailFrom: (string) $container->getParameter('app.mail_from'),
            mailFromName: (string) $container->getParameter('app.mail_from_name'),
            fallbackReply: (string) $container->getParameter('app.openrouter.fallback_reply'),
            mailerDsn: $mailerDsn,
        );
    }
}
