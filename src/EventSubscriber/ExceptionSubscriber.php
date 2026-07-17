<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Exception\RateLimitExceededException;
use App\Exception\ValidationException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\Exception\ValidationFailedException;

final class ExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $environment,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 10],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $throwable = $event->getThrowable();
        $request = $event->getRequest();

        if (!str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        // Let Nelmio Swagger UI / OpenAPI generators render their own responses
        if (str_starts_with($request->getPathInfo(), '/api/doc')) {
            return;
        }

        $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR;
        $message = 'Internal server error';
        $errors = [];

        if ($throwable instanceof ValidationException) {
            $statusCode = Response::HTTP_UNPROCESSABLE_ENTITY;
            $message = $throwable->getMessage();
            $errors = $throwable->errors;
        } elseif ($throwable instanceof RateLimitExceededException) {
            $statusCode = Response::HTTP_TOO_MANY_REQUESTS;
            $message = $throwable->getMessage();
        } elseif ($throwable instanceof ValidationFailedException
            || ($throwable instanceof HttpExceptionInterface
                && $throwable->getPrevious() instanceof ValidationFailedException)
        ) {
            $validation = $throwable instanceof ValidationFailedException
                ? $throwable
                : $throwable->getPrevious();

            $statusCode = Response::HTTP_UNPROCESSABLE_ENTITY;
            $message = 'Validation failed';

            if ($validation instanceof ValidationFailedException) {
                foreach ($validation->getViolations() as $violation) {
                    $errors[] = [
                        'field' => $violation->getPropertyPath(),
                        'message' => (string) $violation->getMessage(),
                    ];
                }
            }
        } elseif ($throwable instanceof HttpExceptionInterface) {
            $statusCode = $throwable->getStatusCode();
            $message = $throwable->getMessage() ?: Response::$statusTexts[$statusCode] ?? 'Error';
        }

        if ($statusCode >= 500) {
            $this->logger->error('Unhandled API exception', [
                'message' => $throwable->getMessage(),
                'exception' => $throwable::class,
                'path' => $request->getPathInfo(),
                'method' => $request->getMethod(),
            ]);

            if ('prod' === $this->environment) {
                $message = 'Internal server error';
            } else {
                $message = $throwable->getMessage();
            }
        } else {
            $this->logger->warning('API client error', [
                'status' => $statusCode,
                'message' => $message,
                'path' => $request->getPathInfo(),
                'method' => $request->getMethod(),
            ]);
        }

        $response = new JsonResponse([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $statusCode);

        if ($throwable instanceof RateLimitExceededException) {
            $response->headers->set('Retry-After', (string) $throwable->retryAfter);
        }

        $event->setResponse($response);
    }
}
