<?php

namespace App\Repository;

use App\Entity\Merchant;
use App\Enum\Status;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Merchant>
 */
class MerchantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Merchant::class);
    }

    public function findByApiKey(string $apiKey): ?Merchant
    {
        return $this->findOneBy(['apiKey' => $apiKey]);
    }

    /**
     * @return Merchant[]
     */
    public function findActive(): array
    {
        return $this->findBy(['status' => Status::Active]);
    }
}
