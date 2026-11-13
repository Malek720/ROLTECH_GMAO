<?php

namespace App\Repository;

use App\Entity\MachineModule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<MachineModule> */
class MachineModuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MachineModule::class);
    }

    /** @return list<MachineModule> */
    public function findForModel(\App\Entity\MachineModel $model): array
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.parent', 'p')->addSelect('p')
            ->leftJoin('m.parts', 'part')->addSelect('part')
            ->andWhere('m.machineModel = :model')
            ->setParameter('model', $model)
            ->orderBy('m.code', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
