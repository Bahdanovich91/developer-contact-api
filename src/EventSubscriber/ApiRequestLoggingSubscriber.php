<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class ApiRequestLoggingSubscriber implements EventSubscriberInterface
{
    private const ATTR_START = '_api_request_started_at';

    public function __construct(
        #[Autowire(service: 'monolog.logger.api')]
        private readonly LoggerInterface $apiLogger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 100],
            KernelEvents::RESPONSE => ['onKernelResponse', -100],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (!str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        $request->attributes->set(
            self::ATTR_START,
            microtime(true)
        );
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (!str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        $startedAt = $request->attributes->get(self::ATTR_START);

        $durationMs = \is_float($startedAt)
            ? (int) round((microtime(true) - $startedAt) * 1000)
            : null;

        $this->apiLogger->info('API request handled', [
            'ip' => $request->getClientIp(),
            'endpoint' => $request->getPathInfo(),
            'method' => $request->getMethod(),
            'status' => $event->getResponse()->getStatusCode(),
            'duration_ms' => $durationMs,
        ]);
    }
}
