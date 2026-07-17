<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Dto\ContactRequestDto;
use App\Service\OpenRouterAiService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\Exception\TimeoutException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class OpenRouterAiServiceTest extends KernelTestCase
{
    protected function tearDown(): void
    {
        self::ensureKernelShutdown();
        parent::tearDown();
    }

    public function testFallbackWhenApiKeyEmpty(): void
    {
        $service = $this->createService(new MockHttpClient(), '');

        $result = $service->analyze($this->createRequestDto());

        self::assertTrue($result->usedFallback);
        self::assertSame('unknown', $result->sentiment);
        self::assertSame('other', $result->category);
        self::assertSame($this->getFallbackReply(), $result->autoReply);
    }

    public function testSuccessfulAnalysis(): void
    {
        $payload = [
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'sentiment' => 'positive',
                            'category' => 'feedback',
                            'autoReply' => 'Thanks for the feedback!',
                        ], JSON_THROW_ON_ERROR),
                    ],
                ],
            ],
        ];

        $service = $this->createService(new MockHttpClient([
            new MockResponse(json_encode($payload, JSON_THROW_ON_ERROR), ['http_code' => 200]),
        ]));

        $result = $service->analyze($this->createRequestDto());

        self::assertFalse($result->usedFallback);
        self::assertSame('positive', $result->sentiment);
        self::assertSame('feedback', $result->category);
        self::assertSame('Thanks for the feedback!', $result->autoReply);
    }

    public function testAnalyzePostsToChatCompletionsEndpoint(): void
    {
        $requestUrl = null;

        $service = $this->createService(new MockHttpClient(function (
            string $method,
            string $url,
        ) use (&$requestUrl): MockResponse {
            $requestUrl = $url;

            return new MockResponse(json_encode([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'sentiment' => 'neutral',
                                'category' => 'support',
                                'autoReply' => 'We will help you soon.',
                            ], JSON_THROW_ON_ERROR),
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR), ['http_code' => 200]);
        }));

        $service->analyze($this->createRequestDto());

        $baseUrl = rtrim((string) static::getContainer()->getParameter('app.openrouter.api_url'), '/');

        self::assertSame($baseUrl.'/chat/completions', $requestUrl);
    }

    public function testFallbackOnHttpError(): void
    {
        $service = $this->createService(new MockHttpClient([
            new MockResponse('unavailable', ['http_code' => 503]),
        ]));

        $result = $service->analyze($this->createRequestDto());

        self::assertTrue($result->usedFallback);
        self::assertSame('unknown', $result->sentiment);
        self::assertSame('other', $result->category);
        self::assertSame($this->getFallbackReply(), $result->autoReply);
    }

    public function testFallbackOnTimeout(): void
    {
        $service = $this->createService(new MockHttpClient(function (): never {
            throw new TimeoutException('Idle timeout reached.');
        }));

        $result = $service->analyze($this->createRequestDto());

        self::assertTrue($result->usedFallback);
        self::assertSame($this->getFallbackReply(), $result->autoReply);
    }

    public function testFallbackOnInvalidJsonInResponse(): void
    {
        $payload = [
            'choices' => [
                [
                    'message' => [
                        'content' => 'not-valid-json',
                    ],
                ],
            ],
        ];

        $service = $this->createService(new MockHttpClient([
            new MockResponse(json_encode($payload, JSON_THROW_ON_ERROR), ['http_code' => 200]),
        ]));

        $result = $service->analyze($this->createRequestDto());

        self::assertTrue($result->usedFallback);
        self::assertSame($this->getFallbackReply(), $result->autoReply);
    }

    public function testIsAvailableReturnsFalseWithoutApiKey(): void
    {
        $service = $this->createService(new MockHttpClient(), '');

        self::assertFalse($service->isAvailable());
    }

    public function testIsAvailableReturnsTrueWhenApiResponds(): void
    {
        $service = $this->createService(new MockHttpClient([
            new MockResponse(json_encode(['data' => []], JSON_THROW_ON_ERROR), ['http_code' => 200]),
        ]));

        self::assertTrue($service->isAvailable());
    }

    public function testIsAvailableReturnsFalseOnUnauthorizedResponse(): void
    {
        $service = $this->createService(new MockHttpClient([
            new MockResponse('unauthorized', ['http_code' => 401]),
        ]));

        self::assertFalse($service->isAvailable());
    }

    private function createService(MockHttpClient $httpClient, ?string $apiKey = null): OpenRouterAiService
    {
        self::bootKernel();
        $container = static::getContainer();
        $container->set(HttpClientInterface::class, $httpClient);

        return new OpenRouterAiService(
            $container->get(HttpClientInterface::class),
            $container->get('monolog.logger.ai'),
            $apiKey ?? 'test-key',
            (string) $container->getParameter('app.openrouter.api_url'),
            (string) $container->getParameter('app.openrouter.model'),
            (int) $container->getParameter('app.openrouter.timeout'),
            (string) $container->getParameter('app.openrouter.fallback_reply'),
        );
    }

    private function createRequestDto(): ContactRequestDto
    {
        return new ContactRequestDto(
            name: 'Jane',
            email: 'jane@example.com',
            phone: '+111111',
            comment: 'Need support please help me.',
        );
    }

    private function getFallbackReply(): string
    {
        if (!self::$booted) {
            self::bootKernel();
        }

        return (string) static::getContainer()->getParameter('app.openrouter.fallback_reply');
    }
}
