<?php

namespace App\DataFixtures;

use App\Entity\Provider;
use App\Enum\Status;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

final class ProviderFixtures extends Fixture
{
    public const PROVIDER_BINANCE = 'provider-binance';
    public const PROVIDER_COINBASE = 'provider-coinbase';
    public const PROVIDER_KRAKEN = 'provider-kraken';
    public const PROVIDER_BYBIT = 'provider-bybit';
    public const PROVIDER_OKX = 'provider-okx';

    public function load(ObjectManager $manager): void
    {
        $binance = (new Provider())
            ->setName('binance')
            ->setData([
                'base_url' => 'https://api.binance.com',
                'timeout' => 5,
                'weight_limit' => 1200,
            ])
            ->setStatus(Status::Active);
        $this->addReference(self::PROVIDER_BINANCE, $binance);
        $manager->persist($binance);

        $coinbase = (new Provider())
            ->setName('coinbase')
            ->setData([
                'base_url' => 'https://api.coinbase.com',
                'timeout' => 5,
                'rate_per_minute' => 100,
            ])
            ->setStatus(Status::Active);
        $this->addReference(self::PROVIDER_COINBASE, $coinbase);
        $manager->persist($coinbase);

        $kraken = (new Provider())
            ->setName('kraken')
            ->setData([
                'base_url' => 'https://api.kraken.com',
                'timeout' => 10,
                'tier' => 'starter',
            ])
            ->setStatus(Status::Active);
        $this->addReference(self::PROVIDER_KRAKEN, $kraken);
        $manager->persist($kraken);

        $bybit = (new Provider())
            ->setName('bybit')
            ->setData([
                'base_url' => 'https://api.bybit.com',
                'timeout' => 5,
                'category' => 'spot',
            ])
            ->setStatus(Status::Active);
        $this->addReference(self::PROVIDER_BYBIT, $bybit);
        $manager->persist($bybit);

        $okx = (new Provider())
            ->setName('okx')
            ->setData([
                'base_url' => 'https://www.okx.com',
                'timeout' => 5,
                'category' => 'spot',
            ])
            ->setStatus(Status::Disabled);
        $this->addReference(self::PROVIDER_OKX, $okx);
        $manager->persist($okx);

        $manager->flush();
    }
}
