<?php

namespace App\Repository;

use App\Entity\Machine;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Machine>
 */
class MachineRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Machine::class);
    }

    /** @return list<Machine> */
    public function findForSite(?\App\Entity\Site $site): array
    {
        if ($site === null) {
            return [];
        }

        return $this->findBy(['site' => $site], ['status' => 'ASC', 'name' => 'ASC']);
    }

    /** @return list<Machine> */
    public function findAllOrdered(): array
    {
        return $this->findBy([], ['status' => 'ASC', 'name' => 'ASC']);
    }

    /** @return list<Machine> */
    public function findVisibleForFaultDeclaration(User $user): array
    {
        if (in_array('ROLE_ADMIN', $user->getRoles(), true) || in_array('ROLE_RESPONSABLE', $user->getRoles(), true)) {
        return $this->createQueryBuilder('m')
            ->join('m.site', 'site')->addSelect('site')
            ->join('m.machineModel', 'model')->addSelect('model')
            ->orderBy('site.name', 'ASC')
            ->addOrderBy('m.name', 'ASC')
            ->getQuery()
            ->getResult();
        }

        return $this->createQueryBuilder('m')
        ->join('m.site', 'site')->addSelect('site')
        ->join('m.machineModel', 'model')->addSelect('model')
        ->andWhere('m.site = :site')
        ->setParameter('site', $user->getSite())
        ->orderBy('m.name', 'ASC')
        ->getQuery()
        ->getResult();
    }

    //    /**
    //     * @return Machine[] Returns an array of Machine objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('m')
    //            ->andWhere('m.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('m.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Machine
    //    {
    //        return $this->createQueryBuilder('m')
    //            ->andWhere('m.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
