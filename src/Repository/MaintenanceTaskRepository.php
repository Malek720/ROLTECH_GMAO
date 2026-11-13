<?php

namespace App\Repository;

use App\Entity\MaintenanceTask;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<MaintenanceTask> */
class MaintenanceTaskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MaintenanceTask::class);
    }

    /** @return list<MaintenanceTask> */
    public function findForModel(\App\Entity\MachineModel $model): array
    {
        return $this->findBy(['machineModel' => $model], ['id' => 'ASC']);
    }
}
