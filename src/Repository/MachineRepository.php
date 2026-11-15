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

    /**
     * KPI loader: every machine with its site and model joined, in one query (no N+1).
     *
     * @return list<Machine>
     */
    public function findAllWithSiteAndModel(): array
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.site', 'site')->addSelect('site')
            ->join('m.machineModel', 'model')->addSelect('model')
            ->orderBy('m.id', 'ASC')
            ->getQuery()
            ->getResult();
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

    /**
     * Machine list page: scoped by role, searchable, filterable.
     *
     * Admin / responsable see every site. A technicien sees only their own site.
     * A technicien with no site gets an empty list (rule 26).
     *
     * This is a NEW method; findVisibleForFaultDeclaration() and search() are
     * unchanged so their tests keep passing.
     *
     * @param array<string, string> $filters  Keys: 'q', 'status', 'model', 'family'
     * @return list<Machine>
     */
    public function findForList(User $user, array $filters = []): array
    {
        $roles         = $user->getRoles();
        $seesEverySite = in_array('ROLE_ADMIN', $roles, true) || in_array('ROLE_RESPONSABLE', $roles, true);

        if (!$seesEverySite && $user->getSite() === null) {
            return [];
        }

        $qb = $this->createQueryBuilder('m')
            ->join('m.machineModel', 'model')->addSelect('model')
            ->leftJoin('m.site', 'site')->addSelect('site')
            ->orderBy('site.name', 'ASC')
            ->addOrderBy('m.name', 'ASC');

        if (!$seesEverySite) {
            $qb->andWhere('m.site = :site')->setParameter('site', $user->getSite());
        }

        // Text search
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $escaped = '%'.addcslashes($q, '\\%_').'%';
            $qb->andWhere('(m.name LIKE :q OR m.serialNumber LIKE :q OR model.code LIKE :q OR model.name LIKE :q)')
               ->setParameter('q', $escaped);
        }

        // Status filter
        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $qb->andWhere('m.status = :status')->setParameter('status', $status);
        }

        // Model filter (by id)
        $modelId = (int) ($filters['model'] ?? 0);
        if ($modelId > 0) {
            $qb->andWhere('model.id = :model')->setParameter('model', $modelId);
        }

        // Family filter
        $family = trim((string) ($filters['family'] ?? ''));
        if ($family !== '') {
            $qb->andWhere('model.family = :family')->setParameter('family', $family);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Global search by name, serial number or model (code/name).
     *
     * Scoping happens in the query, never in the template (rule 26): admin and
     * responsable search every site, a technicien only their own site. A technicien
     * with no site gets an empty list, the same result as "no match", so nothing leaks.
     * Every parameter is bound and LIKE wildcards in the user input are escaped.
     *
     * @return list<Machine>
     */
    public function search(User $user, string $term, int $limit = 20): array
    {
        $roles = $user->getRoles();
        $seesEverySite = in_array('ROLE_ADMIN', $roles, true) || in_array('ROLE_RESPONSABLE', $roles, true);

        if (!$seesEverySite && $user->getSite() === null) {
            return [];
        }

        $query = $this->createQueryBuilder('m')
            ->join('m.machineModel', 'model')->addSelect('model')
            ->leftJoin('m.site', 'site')->addSelect('site')
            // Parentheses keep the OR group from swallowing the site scoping below.
            ->andWhere('(m.name LIKE :term OR m.serialNumber LIKE :term OR model.code LIKE :term OR model.name LIKE :term)')
            ->setParameter('term', '%'.addcslashes($term, '\\%_').'%')
            ->orderBy('m.name', 'ASC')
            ->setMaxResults($limit);

        if (!$seesEverySite) {
            $query->andWhere('m.site = :site')->setParameter('site', $user->getSite());
        }

        return $query->getQuery()->getResult();
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
