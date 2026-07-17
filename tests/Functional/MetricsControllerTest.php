<?php

declare(strict_types=1);

namespace App\Tests\Functional;

final class MetricsControllerTest extends DatabaseWebTestCase
{
    public function testMetricsEndpointReturnsData(): void
    {
        $client = $this->createClientWithDatabase();

        $client->request('GET', '/api/metrics');

        self::assertResponseIsSuccessful();

        $response = json_decode(
            $client->getResponse()->getContent() ?: '',
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        self::assertTrue($response['success']);

        self::assertArrayHasKey('data', $response);

        self::assertArrayHasKey(
            'total',
            $response['data']
        );

        self::assertArrayHasKey(
            'by_sentiment',
            $response['data']
        );

        self::assertArrayHasKey(
            'by_category',
            $response['data']
        );

        self::assertArrayHasKey(
            'last_24h',
            $response['data']
        );

        self::assertArrayHasKey(
            'last_7d',
            $response['data']
        );
    }
}
