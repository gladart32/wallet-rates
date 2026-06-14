<?php

namespace App\DataFixtures;

use App\Entity\Provider;
use App\Entity\Rate;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

final class RateFixtures extends Fixture implements DependentFixtureInterface
{
    private const PROVIDER_MULTIPLIERS = [
        ProviderFixtures::PROVIDER_BINANCE => '1.0000',
        ProviderFixtures::PROVIDER_COINBASE => '0.9975',
        ProviderFixtures::PROVIDER_KRAKEN => '1.0050',
        ProviderFixtures::PROVIDER_BYBIT => '0.9950',
    ];

    private const PAIRS = [
        // Fiat / Fiat
        ['USD', 'EUR', '0.9200'],
        ['USD', 'GBP', '0.7900'],
        ['USD', 'JPY', '154.50'],
        ['USD', 'CHF', '0.8800'],
        ['USD', 'CNY', '7.2400'],
        ['EUR', 'GBP', '0.8600'],
        ['EUR', 'JPY', '167.80'],
        ['USDT', 'RUB', '92.50'],
        ['USDT', 'UAH', '41.20'],
        ['USDT', 'KZT', '495.00'],

        // Crypto / USDT
        ['BTC', 'USDT', '95000'],
        ['ETH', 'USDT', '3500'],
        ['SOL', 'USDT', '195'],
        ['XRP', 'USDT', '2.35'],
        ['BNB', 'USDT', '620'],
        ['ADA', 'USDT', '0.65'],
        ['DOGE', 'USDT', '0.32'],
        ['TON', 'USDT', '5.40'],
        ['TRX', 'USDT', '0.24'],
        ['LTC', 'USDT', '105'],
        ['AVAX', 'USDT', '38'],

        // Crypto / USD
        ['BTC', 'USD', '95000'],
        ['ETH', 'USD', '3500'],
        ['SOL', 'USD', '195'],

        // Cross crypto
        ['BTC', 'ETH', '27.142857142857142857'],
    ];

    public function getDependencies(): array
    {
        return [
            ProviderFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        foreach (self::PROVIDER_MULTIPLIERS as $providerRef => $multiplier) {
            $provider = $this->getReference($providerRef, Provider::class);
            $providerName = $provider->getName();

            foreach (self::PAIRS as [$from, $to, $baseRate]) {
                $value = $this->scale($baseRate, $multiplier);

                $rate = (new Rate())
                    ->setProvider($providerName)
                    ->setCurrencyFrom($from)
                    ->setCurrencyTo($to)
                    ->setValue($value);

                $manager->persist($rate);
            }
        }

        $manager->flush();
    }

    private function scale(string $base, string $multiplier): string
    {
        $value = bcmul($base, $multiplier, 18);

        return $this->normalize($value);
    }

    private function normalize(string $value): string
    {
        $value = rtrim(rtrim($value, '0'), '.');

        if ($value === '' || $value === '-') {
            $value = '0';
        }

        if (!str_contains($value, '.')) {
            $value .= '.0';
        }

        return $value;
    }
}
