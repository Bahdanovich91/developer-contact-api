<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class HealthControllerTest extends DatabaseWebTestCase
{
    public function testHealthEndpointReturnsValidResponse(): void
    {
        $client = $this->createClientWithDatabase();

        static::getContainer()->set(HttpClientInterface::class, new MockHttpClient([
            new MockResponse(json_encode(['data' => []], JSON_THROW_ON_ERROR)),
        ]));

        $client->request('GET', '/api/health');

        self::assertResponseIsSuccessful();

        $response = json_decode(
            $client->getResponse()->getContent() ?: '',
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        self::assertTrue($response['success']);

        self::assertArrayHasKey('data', $response);
        self::assertArrayHasKey('status', $response['data']);
        self::assertArrayHasKey('checks', $response['data']);
        self::assertArrayHasKey('timestamp', $response['data']);

        self::assertArrayHasKey(
            'database',
            $response['data']['checks']
        );

        self::assertArrayHasKey(
            'mailer',
            $response['data']['checks']
        );
    }
}
