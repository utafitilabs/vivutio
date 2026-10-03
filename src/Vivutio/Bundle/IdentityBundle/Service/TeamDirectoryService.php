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

namespace Vivutio\Bundle\IdentityBundle\Service;

use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Uid\Uuid;
use Vivutio\Bundle\IdentityBundle\Entity\User;
use Vivutio\Bundle\IdentityBundle\Enum\TierEnum;
use Vivutio\Bundle\IdentityBundle\Model\TeamPage;
use Vivutio\Bundle\IdentityBundle\Model\TeamQuery;
use Vivutio\Bundle\IdentityBundle\Repository\UserRepository;

/**
 * The team list: who matches a query, a page of them, and the counts its
 * filters show.
 *
 * What a viewer may use is passed in, never inferred here: whether they see
 * tiers decides whether a tier in the query is honoured, and whether they may
 * read addresses decides whether the search reads addresses. A filter the
 * viewer may not see would otherwise sort people by what is hidden from them.
 */
final readonly class TeamDirectoryService
{
    public const int PER_PAGE = 25;

    public function __construct(
        private UserRepository $users,
    ) {
    }

    public function page(TeamQuery $query, bool $withTiers, bool $withAddresses): TeamPage
    {
        $matching = $this->matching($query, $withTiers, $withAddresses);

        $total = (int) (clone $matching)->select('COUNT(u.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();

        /** @var list<User> $people */
        $people = $matching
            ->setFirstResult(($query->page - 1) * self::PER_PAGE)
            ->setMaxResults(self::PER_PAGE)
            ->getQuery()
            ->getResult();

        return new TeamPage(
            people: $people,
            total: $total,
            page: $query->page,
            perPage: self::PER_PAGE,
            everyone: $this->users->count([]),
            tierCounts: $withTiers ? $this->tierCounts() : null,
            positions: $this->positionCounts(),
            noPosition: $this->users->count(['position' => null]),
            active: $this->users->count(['active' => true]),
        );
    }

    private function matching(TeamQuery $query, bool $withTiers, bool $withAddresses): QueryBuilder
    {
        $matching = $this->users->createQueryBuilder('u')
            ->leftJoin('u.position', 'p')
            ->addSelect('p')
            ->orderBy('u.firstName')
            ->addOrderBy('u.lastName')
            ->addOrderBy('u.id');

        if ('' !== $query->q) {
            $name = $matching->expr()->like("LOWER(CONCAT(u.firstName, ' ', u.lastName))", ':q');
            $matching->andWhere($withAddresses ? $matching->expr()->orX($name, $matching->expr()->like('u.email', ':q')) : $name)
                ->setParameter('q', '%'.addcslashes(mb_strtolower($query->q), '%_\\').'%');
        }

        if ($withTiers && null !== $query->tier) {
            $matching->andWhere('u.tier = :tier')->setParameter('tier', $query->tier);
        }

        if (TeamQuery::NO_POSITION === $query->position) {
            $matching->andWhere('u.position IS NULL');
        } elseif (null !== $query->position) {
            $matching->andWhere('p.uuid = :position')->setParameter('position', Uuid::isValid($query->position) ? Uuid::fromString($query->position) : Uuid::v7(), 'uuid');
        }

        if (null !== $query->account) {
            $matching->andWhere('u.active = :active')->setParameter('active', TeamQuery::ACTIVE === $query->account);
        }

        return $matching;
    }

    /**
     * @return array<string, int> tier value to how many hold it, every tier present
     */
    private function tierCounts(): array
    {
        $counts = [];
        foreach (TierEnum::cases() as $tier) {
            $counts[$tier->value] = $this->users->count(['tier' => $tier]);
        }

        return $counts;
    }

    /**
     * @return list<array{uuid: string, name: string, count: int}>
     */
    private function positionCounts(): array
    {
        /** @var list<array{uuid: Uuid, name: string, held: int|string}> $rows */
        $rows = $this->users->createQueryBuilder('u')
            ->select('p.uuid AS uuid', 'p.name AS name', 'COUNT(u.id) AS held')
            ->innerJoin('u.position', 'p')
            ->groupBy('p.id')
            ->addGroupBy('p.uuid')
            ->addGroupBy('p.name')
            ->orderBy('p.name')
            ->getQuery()
            ->getResult();

        return array_map(static fn (array $row): array => [
            'uuid' => (string) $row['uuid'],
            'name' => $row['name'],
            'count' => (int) $row['held'],
        ], $rows);
    }
}
