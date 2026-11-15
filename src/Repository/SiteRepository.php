<?php

namespace App\Repository;

use App\Entity\Site;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Site> */
class SiteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Site::class);
    }

    /**
     * Eager-load machines (and their model) for a single site.
     * Used by the site page controller (SiteController) and the map data provider.
     */
    public function findOneWithMachines(int $id): ?Site
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.machines', 'm')->addSelect('m')
            ->leftJoin('m.machineModel', 'model')->addSelect('model')
            ->andWhere('s.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
