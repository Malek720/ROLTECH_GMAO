<?php

namespace App\Repository;

use App\Entity\Intervention;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Intervention>
 */
class InterventionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Intervention::class);
    }

    public function countOpenForTechnician(\App\Entity\User $technician): int
    {
        return (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->andWhere('i.technician = :technician')
            ->andWhere('i.status IN (:statuses)')
            ->setParameter('technician', $technician)
            ->setParameter('statuses', ['planifiee', 'en cours'])
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function hasOpenPreventiveForSchedule(\App\Entity\MaintenanceSchedule $schedule): bool
    {
        return (bool) $this->createQueryBuilder('i')
            ->select('1')
            ->andWhere('i.schedule = :schedule')
            ->andWhere('i.type = :type')
            ->andWhere('i.status IN (:statuses)')
            ->setParameter('schedule', $schedule)
            ->setParameter('type', 'preventive')
            ->setParameter('statuses', ['planifiee', 'en cours'])
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return list<Intervention> */
    public function findForTechnician(\App\Entity\User $technician, ?string $status = null): array
    {
        $query = $this->createQueryBuilder('i')
            ->leftJoin('i.fault', 'f')->addSelect('f')
            ->leftJoin('i.schedule', 's')->addSelect('s')
            ->andWhere('i.technician = :technician')
            ->setParameter('technician', $technician)
            ->orderBy('i.status', 'ASC')
            ->addOrderBy('i.startDate', 'DESC');
        if ($status !== null) {
            $query->andWhere('i.status = :status')->setParameter('status', $status);
        }

        return $query->getQuery()->getResult();
    }

    /** @return list<Intervention> */
    public function findForDispatch(): array
    {
        return $this->createQueryBuilder('i')
            ->leftJoin('i.fault', 'f')->addSelect('f')
            ->leftJoin('i.schedule', 's')->addSelect('s')
            ->leftJoin('f.machine', 'fm')->addSelect('fm')
            ->leftJoin('s.machine', 'sm')->addSelect('sm')
            ->andWhere('i.status = :planned')
            ->setParameter('planned', 'planifiee')
            ->addOrderBy("CASE WHEN f.severity = 'critique' THEN 0 WHEN f.severity = 'moyenne' THEN 1 ELSE 2 END", 'ASC')
            ->addOrderBy('i.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<Intervention> */
    public function findForDispatchBySite(\App\Entity\Site $site): array
    {
        return $this->createQueryBuilder('i')
            ->leftJoin('i.fault', 'f')->addSelect('f')
            ->leftJoin('i.schedule', 's')->addSelect('s')
            ->leftJoin('f.machine', 'fm')
            ->leftJoin('s.machine', 'sm')
            ->andWhere('(fm.site = :site OR sm.site = :site)')
            ->andWhere('i.status = :planned')
            ->setParameter('site', $site)
            ->setParameter('planned', 'planifiee')
            ->addOrderBy("CASE WHEN f.severity = 'critique' THEN 0 WHEN f.severity = 'moyenne' THEN 1 ELSE 2 END", 'ASC')
            ->addOrderBy('i.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    //    /**
    //     * @return Intervention[] Returns an array of Intervention objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('i')
    //            ->andWhere('i.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('i.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Intervention
    //    {
    //        return $this->createQueryBuilder('i')
    //            ->andWhere('i.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
