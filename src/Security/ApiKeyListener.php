<?php

declare(strict_types=1);

namespace App\Security;

use App\Enum\Status;
use App\Repository\MerchantRepository;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: KernelEvents::CONTROLLER, priority: 100)]
final class ApiKeyListener
{
    private const PROTECTED_PREFIX = '/api/';
    private const UNPROTECTED_PREFIX = '/api/doc';

    public function __construct(
        private readonly MerchantRepository $merchantRepository,
        private readonly SignatureBuilder $signatureBuilder,
    ) {
    }

    public function __invoke(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (!str_starts_with($path, self::PROTECTED_PREFIX)) {
            return;
        }

        if (str_starts_with($path, self::UNPROTECTED_PREFIX)) {
            return;
        }

        $apiKey = $request->headers->get('X-API-Key');
        if (!is_string($apiKey) || $apiKey === '') {
            throw new UnauthorizedHttpException(
                'Bearer realm="api"',
                'Missing X-API-Key header.',
            );
        }

        $merchant = $this->merchantRepository->findByApiKey($apiKey);
        if ($merchant === null || $merchant->getStatus() !== Status::Active) {
            throw new UnauthorizedHttpException(
                'Bearer realm="api"',
                'Invalid or inactive API key.',
            );
        }

        $signature = $request->headers->get('X-API-Signature');
        if (!is_string($signature) || $signature === '') {
            throw new UnauthorizedHttpException(
                'Bearer realm="api"',
                'Missing X-API-Signature header.',
            );
        }

        if (!$this->signatureBuilder->verify($request, $merchant->getApiSecret(), $signature)) {
            throw new UnauthorizedHttpException(
                'Bearer realm="api"',
                'Invalid request signature.',
            );
        }

        $request->attributes->set('app.merchant', $merchant);
    }
}
