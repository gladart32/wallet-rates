<?php

namespace App\DataFixtures;

use App\Entity\Merchant;
use App\Enum\Status;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

final class MerchantFixtures extends Fixture
{
    public const MERCHANT_ACME = 'merchant-acme';
    public const MERCHANT_EURO = 'merchant-euro';
    public const MERCHANT_LEGACY = 'merchant-legacy';

    public function load(ObjectManager $manager): void
    {
        $acme = (new Merchant())
            ->setName('Acme Payments')
            ->setApiKey('mk_acme_dev_key_0001')
            ->setApiSecret('sk_acme_dev_secret_8a1b6f2c4e9d3a7b')
            ->setBaseCurrency('USD')
            ->setStatus(Status::Active);
        $this->addReference(self::MERCHANT_ACME, $acme);
        $manager->persist($acme);

        $euro = (new Merchant())
            ->setName('EuroGateway')
            ->setApiKey('mk_euro_dev_key_0002')
            ->setApiSecret('sk_euro_dev_secret_2c7e4d1f9a6b8c03')
            ->setBaseCurrency('EUR')
            ->setStatus(Status::Active);
        $this->addReference(self::MERCHANT_EURO, $euro);
        $manager->persist($euro);

        $legacy = (new Merchant())
            ->setName('LegacyLtd')
            ->setApiKey('mk_legacy_dev_key_0003')
            ->setApiSecret('sk_legacy_dev_secret_5f8a2b6c1d3e7f09')
            ->setBaseCurrency('GBP')
            ->setStatus(Status::Disabled);
        $this->addReference(self::MERCHANT_LEGACY, $legacy);
        $manager->persist($legacy);

        $manager->flush();
    }
}
