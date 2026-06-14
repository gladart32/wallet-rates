<?php

namespace App\Repository;

use App\Entity\Rate;
use App\Enum\Status;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Rate>
 */
class RateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Rate::class);
    }

    /**
     * @return Rate[]
     */
    public function findByProviderName(string $name): array
    {
        return $this->findBy(['provider' => $name]);
    }

    public function findLatestByPair(string $currencyFrom, string $currencyTo): ?Rate
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.currencyFrom = :from')
            ->andWhere('r.currencyTo = :to')
            ->andWhere('r.status = :status')
            ->setParameter('from', $currencyFrom)
            ->setParameter('to', $currencyTo)
            ->setParameter('status', Status::Active->value)
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findActivePair(string $currencyFrom, string $currencyTo): ?Rate
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.currencyFrom = :from')
            ->andWhere('r.currencyTo = :to')
            ->andWhere('r.status = :status')
            ->setParameter('from', $currencyFrom)
            ->setParameter('to', $currencyTo)
            ->setParameter('status', Status::Active->value)
            ->orderBy('r.updatedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return Rate[]
     */
    public function findActive(): array
    {
        return $this->findBy(['status' => Status::Active]);
    }
}
