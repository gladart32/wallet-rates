<?php

namespace App\Repository;

use App\Entity\Provider;
use App\Enum\Status;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Provider>
 */
class ProviderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Provider::class);
    }

    public function findOneByName(string $name): ?Provider
    {
        return $this->findOneBy(['name' => $name]);
    }

    /**
     * @return Provider[]
     */
    public function findEnabled(): array
    {
        return $this->findBy(['status' => Status::Active]);
    }
}
