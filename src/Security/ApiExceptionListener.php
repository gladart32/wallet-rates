<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::EXCEPTION, priority: 0)]
final class ApiExceptionListener
{
    private const API_PREFIX = '/api/';

    public function __invoke(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (!str_starts_with($path, self::API_PREFIX)) {
            return;
        }

        if (str_starts_with($path, '/api/doc')) {
            return;
        }

        $exception = $event->getThrowable();
        if (!$exception instanceof HttpExceptionInterface) {
            return;
        }

        $errorCode = $this->resolveErrorCode($exception);
        $statusCode = $exception->getStatusCode();
        $headers = $exception->getHeaders();

        $response = new JsonResponse(
            [
                'error' => $errorCode,
                'message' => $exception->getMessage(),
            ],
            $statusCode,
            $headers,
        );

        $event->setResponse($response);
    }

    private function resolveErrorCode(\Throwable $exception): string
    {
        return match ($exception->getStatusCode()) {
            400 => 'bad_request',
            401 => 'unauthorized',
            403 => 'forbidden',
            404 => 'not_found',
            405 => 'method_not_allowed',
            409 => 'conflict',
            422 => 'unprocessable_entity',
            429 => 'too_many_requests',
            default => 'error',
        };
    }
}
