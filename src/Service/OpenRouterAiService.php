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
    private const CHAT_COMPLETIONS_PATH = '/chat/completions';
    private const MODELS_PATH = '/models';

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
            $response = $this->httpClient->request('POST', $this->getChatCompletionsUrl(), [
                'timeout' => $this->timeout,
                'headers' => $this->buildAuthHeaders(),
                'json' => [
                    'model' => $this->model,
                    // OpenRouter reserves the full max_tokens budget against account credits.
                    // Without an explicit limit the provider default (e.g. 16384) can yield HTTP 402
                    // even when GET /models succeeds and a small completion would fit the balance.
                    'max_tokens' => 500,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Analyze customer contact message. Return JSON only with keys: '
                                .'sentiment (positive|negative|neutral|unknown), '
                                .'category (support|sales|feedback|other), '
                                .'autoReply (short polite reply in the same language as the comment).',
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

            $statusCode = $response->getStatusCode();
            if ($statusCode >= 400) {
                $this->aiLogger->error('OpenRouter returned HTTP error', [
                    'status_code' => $statusCode,
                    'model' => $this->model,
                    'body' => mb_substr($response->getContent(false), 0, 500),
                ]);

                throw new \RuntimeException(sprintf('OpenRouter returned HTTP %d', $statusCode));
            }

            $data = $response->toArray(false);

            $content = $data['choices'][0]['message']['content'] ?? null;
            if (!is_string($content)) {
                throw new \RuntimeException('Invalid OpenRouter response: missing message content');
            }

            $result = json_decode(
                $content,
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            if (!is_array($result)) {
                throw new \RuntimeException('Invalid OpenRouter response: expected JSON object');
            }

            $sentiment = $result['sentiment'] ?? 'unknown';
            $category = $result['category'] ?? 'other';
            $autoReply = $result['autoReply'] ?? $this->fallbackReply;

            $this->aiLogger->info('OpenRouter analysis completed', [
                'model' => $this->model,
                'sentiment' => $sentiment,
                'category' => $category,
            ]);

            return new AiAnalysisResult(
                sentiment: $sentiment,
                category: $category,
                autoReply: $autoReply,
            );

        } catch (ExceptionInterface $exception) {
            $this->aiLogger->error('OpenRouter HTTP request failed', [
                'error' => $exception->getMessage(),
                'model' => $this->model,
            ]);

            return AiAnalysisResult::fallback($this->fallbackReply);
        } catch (\JsonException $exception) {
            $this->aiLogger->error('OpenRouter response JSON parsing failed', [
                'error' => $exception->getMessage(),
                'model' => $this->model,
            ]);

            return AiAnalysisResult::fallback($this->fallbackReply);
        } catch (\Throwable $exception) {
            $this->aiLogger->error('OpenRouter analysis failed', [
                'error' => $exception->getMessage(),
                'model' => $this->model,
            ]);

            return AiAnalysisResult::fallback($this->fallbackReply);
        }
    }

    public function isAvailable(): bool
    {
        if ('' === trim($this->apiKey)) {
            return false;
        }

        try {
            $response = $this->httpClient->request('GET', $this->getModelsUrl(), [
                'timeout' => min(5, $this->timeout),
                'headers' => $this->buildAuthHeaders(),
            ]);

            $statusCode = $response->getStatusCode();

            return $statusCode >= 200 && $statusCode < 400;
        } catch (\Throwable $exception) {
            $this->aiLogger->warning('OpenRouter availability check failed', [
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @return array<string, string>
     */
    private function buildAuthHeaders(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->apiKey,
            'Content-Type' => 'application/json',
        ];
    }

    private function getChatCompletionsUrl(): string
    {
        return $this->buildApiUrl(self::CHAT_COMPLETIONS_PATH);
    }

    private function getModelsUrl(): string
    {
        return $this->buildApiUrl(self::MODELS_PATH);
    }

    private function buildApiUrl(string $path): string
    {
        $baseUrl = rtrim($this->apiUrl, '/');

        if (str_ends_with($baseUrl, $path)) {
            return $baseUrl;
        }

        return $baseUrl.$path;
    }
}
