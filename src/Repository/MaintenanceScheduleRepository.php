<?php

namespace App\Repository;

use App\Entity\MaintenanceSchedule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<MaintenanceSchedule> */
class MaintenanceScheduleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MaintenanceSchedule::class);
    }

    /** @return list<MaintenanceSchedule> */
    public function findAllForPlanning(): array
    {
        return $this->findForPlanning();
    }

    /** @return list<MaintenanceSchedule> */
    public function findForPlanning(?int $siteId = null): array
    {
        $query = $this->createQueryBuilder('s')
            ->join('s.machine', 'm')->addSelect('m')
            ->join('s.task', 't')->addSelect('t')
            ->andWhere('s.archivedAt IS NULL')
            ->orderBy('s.nextDueDate', 'ASC')
        ;
        if ($siteId !== null) {
            $query->andWhere('m.site = :site')->setParameter('site', $siteId);
        }

        return $query->getQuery()->getResult();
    }

    /** @return list<MaintenanceSchedule> */
    public function findOverdueForSite(?\App\Entity\Site $site): array
    {
        $query = $this->createQueryBuilder('s')
            ->join('s.machine', 'm')->addSelect('m')
            ->join('s.task', 't')->addSelect('t')
            ->andWhere('s.state = :state')
            ->andWhere('s.archivedAt IS NULL')
            ->setParameter('state', 'echue')
            ->orderBy('s.nextDueDate', 'ASC');
        if ($site === null) {
            // No site predicate means an admin sees schedules from every site.
        } else {
            $query->andWhere('m.site = :site')->setParameter('site', $site);
        }

        return $query->getQuery()->getResult();
    }

    public function findActiveForMachineAndTask(\App\Entity\Machine $machine, \App\Entity\MaintenanceTask $task): ?MaintenanceSchedule
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.machine = :machine')
            ->andWhere('s.task = :task')
            ->andWhere('s.archivedAt IS NULL')
            ->setParameter('machine', $machine)
            ->setParameter('task', $task)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
