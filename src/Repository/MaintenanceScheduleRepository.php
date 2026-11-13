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
        if ($site === null) {
            return [];
        }

        return $this->createQueryBuilder('s')
            ->join('s.machine', 'm')->addSelect('m')
            ->join('s.task', 't')->addSelect('t')
            ->andWhere('m.site = :site')
            ->andWhere('s.state = :state')
            ->andWhere('s.archivedAt IS NULL')
            ->setParameter('site', $site)
            ->setParameter('state', 'echue')
            ->orderBy('s.nextDueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
