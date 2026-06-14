<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Rate;
use App\Enum\Status;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'RateResponse',
    description: 'Курс валюты, предоставленный провайдером.',
    required: ['id', 'provider', 'currencyFrom', 'currencyTo', 'value', 'status', 'createdAt', 'updatedAt'],
)]
final class RateResponse
{
    public function __construct(
        #[OA\Property(description: 'ID курса.', example: 1)]
        public readonly string $id,
        #[OA\Property(description: 'Имя провайдера.', example: 'binance')]
        public readonly string $provider,
        #[OA\Property(description: 'Исходная валюта.', example: 'USD')]
        public readonly string $currencyFrom,
        #[OA\Property(description: 'Целевая валюта.', example: 'EUR')]
        public readonly string $currencyTo,
        #[OA\Property(description: 'Значение курса (decimal, до 18 знаков).', example: '0.9200')]
        public readonly string $value,
        #[OA\Property(description: 'Статус курса.', enum: ['active', 'disabled', 'deleted'], example: 'active')]
        public readonly string $status,
        #[OA\Property(description: 'Дата создания (ISO-8601).', example: '2026-06-14T10:00:00+00:00')]
        public readonly string $createdAt,
        #[OA\Property(description: 'Дата обновления (ISO-8601).', example: '2026-06-14T10:00:00+00:00')]
        public readonly string $updatedAt,
    ) {
    }

    public static function fromEntity(Rate $rate): self
    {
        return new self(
            id: (string) $rate->getId(),
            provider: (string) $rate->getProvider(),
            currencyFrom: (string) $rate->getCurrencyFrom(),
            currencyTo: (string) $rate->getCurrencyTo(),
            value: (string) $rate->getValue(),
            status: ($rate->getStatus() ?? Status::Active)->value,
            createdAt: $rate->getCreatedAt()?->format(\DateTimeInterface::ATOM) ?? '',
            updatedAt: $rate->getUpdatedAt()?->format(\DateTimeInterface::ATOM) ?? '',
        );
    }
}
