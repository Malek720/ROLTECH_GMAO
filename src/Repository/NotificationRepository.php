<?php

namespace App\Repository;

use App\Entity\Notification;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Notification> */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    /** @return list<\App\Entity\Notification> */
    public function findForUser(\App\Entity\User $user): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.recipient = :recipient')
            ->setParameter('recipient', $user)
            ->orderBy('n.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countUnreadForUser(\App\Entity\User $user): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->andWhere('n.recipient = :recipient')
            ->andWhere('n.readAt IS NULL')
            ->setParameter('recipient', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countForUser(\App\Entity\User $user): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->andWhere('n.recipient = :recipient')
            ->setParameter('recipient', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
