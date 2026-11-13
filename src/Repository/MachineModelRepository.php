<?php

namespace App\Repository;

use App\Entity\MachineModel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\MaintenanceSchedule;

/** @extends ServiceEntityRepository<MachineModel> */
class MachineModelRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MachineModel::class);
    }

    public function countBlockingSchedules(MachineModel $model): int
    {
        return (int) $this->getEntityManager()->createQueryBuilder()
            ->select('COUNT(s.id)')
            ->from(MaintenanceSchedule::class, 's')
            ->join('s.task', 't')
            ->andWhere('t.machineModel = :model')
            ->setParameter('model', $model)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
