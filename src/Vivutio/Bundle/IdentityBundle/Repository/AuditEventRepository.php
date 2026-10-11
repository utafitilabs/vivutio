<?php

declare(strict_types=1);

/*
 * This file is part of the vivutio core.
 *
 * (c) Ezekiel Mjema <https://github.com/eemjema>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Vivutio\Bundle\IdentityBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Vivutio\Bundle\IdentityBundle\Entity\AuditEvent;
use Vivutio\Bundle\IdentityBundle\Enum\AuditKindEnum;

/**
 * @extends ServiceEntityRepository<AuditEvent>
 */
class AuditEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditEvent::class);
    }

    /**
     * The lines of a kind, since a moment, whose words, person or address hold
     * the words searched for, newest first.
     *
     * @return list<AuditEvent>
     */
    public function findMatching(?AuditKindEnum $kind, ?\DateTimeImmutable $since, string $words, int $limit, int $offset): array
    {
        /** @var list<AuditEvent> $found */
        $found = $this->filtered($kind, $since, $words)
            ->orderBy('e.at', 'DESC')
            ->addOrderBy('e.id', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();

        return $found;
    }

    public function countMatching(?AuditKindEnum $kind, ?\DateTimeImmutable $since, string $words): int
    {
        return (int) $this->filtered($kind, $since, $words)->select('COUNT(e.id)')->getQuery()->getSingleScalarResult();
    }

    private function filtered(?AuditKindEnum $kind, ?\DateTimeImmutable $since, string $words): QueryBuilder
    {
        $query = $this->createQueryBuilder('e');
        if (null !== $kind) {
            $query->andWhere('e.kind = :kind')->setParameter('kind', $kind);
        }
        if (null !== $since) {
            $query->andWhere('e.at >= :since')->setParameter('since', $since);
        }
        if ('' !== $words) {
            $query->andWhere('LOWER(e.said) LIKE :words OR LOWER(e.actorName) LIKE :words OR e.address LIKE :words')
                ->setParameter('words', '%'.addcslashes(mb_strtolower($words), '%_').'%');
        }

        return $query;
    }
}
