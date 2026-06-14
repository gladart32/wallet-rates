<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\RateResponse;
use App\Entity\Merchant;
use App\Repository\RateRepository;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1', name: 'api_v1_rates_')]
final class RateController
{
    public function __construct(
        private readonly RateRepository $rateRepository,
    ) {
    }

    #[Route('/rates', name: 'list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/rates',
        operationId: 'listRates',
        summary: 'Список всех активных курсов',
        description: 'Возвращает все курсы со статусом `active`. Дубликаты по провайдеру исключены (по паре валют).',
        tags: ['Rates'],
    )]
    #[OA\Response(
        response: 200,
        description: 'Список курсов.',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/RateResponse'),
        ),
    )]
    #[OA\Response(
        response: 401,
        description: 'Невалидный или отсутствующий API-ключ, либо подпись.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'unauthorized'),
                new OA\Property(property: 'message', type: 'string', example: 'Missing X-API-Key header.'),
            ],
        ),
    )]
    public function list(): JsonResponse
    {
        $rates = $this->rateRepository->findActive();

        return new JsonResponse(
            array_map(RateResponse::fromEntity(...), $rates),
            JsonResponse::HTTP_OK,
        );
    }

    #[Route(
        '/rates/{currency}',
        name: 'get',
        methods: ['GET'],
        requirements: ['currency' => '[A-Za-z]{2,16}'],
    )]
    #[OA\Get(
        path: '/api/v1/rates/{currency}',
        operationId: 'getRateByCurrency',
        summary: 'Получить активный курс для валюты относительно базовой валюты мерчанта',
        description: 'Возвращает активный курс пары `merchant.baseCurrency → currency`. Регистр валюты не важен.',
        tags: ['Rates'],
    )]
    #[OA\Parameter(
        name: 'currency',
        in: 'path',
        description: 'Код целевой валюты (например, `EUR`).',
        required: true,
        schema: new OA\Schema(type: 'string', minLength: 2, maxLength: 16, example: 'EUR'),
    )]
    #[OA\Response(
        response: 200,
        description: 'Курс для пары.',
        content: new OA\JsonContent(ref: '#/components/schemas/RateResponse'),
    )]
    #[OA\Response(
        response: 401,
        description: 'Невалидный или отсутствующий API-ключ, либо подпись.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'unauthorized'),
                new OA\Property(property: 'message', type: 'string', example: 'Missing X-API-Key header.'),
            ],
        ),
    )]
    #[OA\Response(
        response: 404,
        description: 'Активный курс для пары не найден.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'rate_not_found'),
                new OA\Property(property: 'message', type: 'string', example: 'No active rate for USD -> EUR.'),
            ],
        ),
    )]
    public function getOne(string $currency, Request $request): JsonResponse
    {
        $currency = strtoupper($currency);
        $merchant = $request->attributes->get('app.merchant');
        if (!$merchant instanceof Merchant) {
            throw new NotFoundHttpException('Merchant context is missing.');
        }

        $base = (string) $merchant->getBaseCurrency();
        $rate = $this->rateRepository->findActivePair($base, $currency);

        if ($rate === null) {
            throw new NotFoundHttpException(sprintf('No active rate for %s -> %s.', $base, $currency));
        }

        return new JsonResponse(
            RateResponse::fromEntity($rate),
            JsonResponse::HTTP_OK,
        );
    }
}
