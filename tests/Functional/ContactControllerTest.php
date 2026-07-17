<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ContactControllerTest extends DatabaseWebTestCase
{
    public function testCreateContact(): void
    {
        $client = $this->createClientWithDatabase();

        static::getContainer()->set(HttpClientInterface::class, new MockHttpClient([
            new MockResponse(json_encode([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'sentiment' => 'neutral',
                                'category' => 'support',
                                'autoReply' => 'Thank you for your message.',
                            ], JSON_THROW_ON_ERROR),
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR)),
        ]));

        $client->request(
            'POST',
            '/api/contact',
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'phone' => '+48123123123',
                'comment' => 'Functional test message',
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(201);

        $response = json_decode(
            $client->getResponse()->getContent() ?: '',
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        self::assertTrue($response['success']);

        self::assertArrayHasKey('data', $response);

        self::assertSame(
            'Test User',
            $response['data']['name']
        );

        self::assertSame(
            'test@example.com',
            $response['data']['email']
        );
    }

    public function testCreateContactValidationError(): void
    {
        $client = $this->createClientWithDatabase();

        $client->request(
            'POST',
            '/api/contact',
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: '{}'
        );

        self::assertResponseStatusCodeSame(422);

        $response = json_decode(
            $client->getResponse()->getContent() ?: '',
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        self::assertFalse($response['success']);

        self::assertArrayHasKey(
            'errors',
            $response
        );
    }
}
