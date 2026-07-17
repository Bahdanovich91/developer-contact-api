<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\AiAnalysisResult;
use App\Dto\ContactRequestDto;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class OpenRouterAiService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,

        #[Autowire(service: 'monolog.logger.ai')]
        private readonly LoggerInterface $aiLogger,

        #[Autowire('%app.openrouter.api_key%')]
        private readonly string $apiKey,

        #[Autowire('%app.openrouter.api_url%')]
        private readonly string $apiUrl,

        #[Autowire('%app.openrouter.model%')]
        private readonly string $model,

        #[Autowire('%app.openrouter.timeout%')]
        private readonly int $timeout,

        #[Autowire('%app.openrouter.fallback_reply%')]
        private readonly string $fallbackReply,
    ) {
    }

    public function analyze(ContactRequestDto $dto): AiAnalysisResult
    {
        if ('' === trim($this->apiKey)) {
            $this->aiLogger->warning('OpenRouter API key is empty, using fallback');

            return AiAnalysisResult::fallback($this->fallbackReply);
        }

        try {
            $response = $this->httpClient->request('POST', $this->apiUrl, [
                'timeout' => $this->timeout,
                'headers' => [
                    'Authorization' => 'Bearer '.$this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $this->model,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Analyze customer contact message. Return JSON only with sentiment, category and autoReply.',
                        ],
                        [
                            'role' => 'user',
                            'content' => json_encode([
                                'name' => $dto->name,
                                'email' => $dto->email,
                                'phone' => $dto->phone,
                                'comment' => $dto->comment,
                            ], JSON_THROW_ON_ERROR),
                        ],
                    ],
                ],
            ]);

            $data = $response->toArray();

            $content = $data['choices'][0]['message']['content'] ?? null;

            if (!is_string($content)) {
                throw new \RuntimeException('Invalid OpenRouter response');
            }

            $result = json_decode(
                $content,
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            $this->aiLogger->info('OpenRouter analysis completed', [
                'model' => $this->model,
            ]);

            return new AiAnalysisResult(
                sentiment: $result['sentiment'] ?? 'unknown',
                category: $result['category'] ?? 'other',
                autoReply: $result['autoReply'] ?? $this->fallbackReply,
            );

        } catch (ExceptionInterface|\JsonException|\Throwable $exception) {
            $this->aiLogger->error('OpenRouter analysis failed', [
                'message' => $exception->getMessage(),
            ]);

            return AiAnalysisResult::fallback($this->fallbackReply);
        }
    }
}
