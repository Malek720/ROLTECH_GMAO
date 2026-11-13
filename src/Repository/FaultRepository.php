<?php

namespace App\Repository;

use App\Entity\Fault;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Fault>
 */
class FaultRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Fault::class);
    }

    /** @return list<Fault> */
    public function findToDispatch(?\App\Entity\Site $site): array
    {
        if ($site === null) {
            return [];
        }

        return $this->createQueryBuilder('f')
            ->join('f.machine', 'm')->addSelect('m')
            ->leftJoin('f.interventions', 'i', 'WITH', 'i.status IN (:openStatuses)')
            ->andWhere('m.site = :site')
            ->andWhere('f.status IN (:faultStatuses)')
            ->andWhere('i.technician IS NULL')
            ->setParameter('site', $site)
            ->setParameter('openStatuses', ['planifiee', 'en cours'])
            ->setParameter('faultStatuses', ['ouverte', 'en cours'])
            ->orderBy('f.severity', 'ASC')
            ->addOrderBy('f.reportedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    //    /**
    //     * @return Fault[] Returns an array of Fault objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('f')
    //            ->andWhere('f.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('f.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Fault
    //    {
    //        return $this->createQueryBuilder('f')
    //            ->andWhere('f.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
