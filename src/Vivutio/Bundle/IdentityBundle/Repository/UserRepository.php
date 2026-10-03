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
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;
use Vivutio\Bundle\IdentityBundle\Entity\User;
use Vivutio\Bundle\IdentityBundle\Enum\TierEnum;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function findOneByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => strtolower($email)]);
    }

    public function countActiveSuperAdmins(): int
    {
        return $this->count(['tier' => TierEnum::SuperAdmin, 'active' => true]);
    }

    /**
     * The active Super Admins, with their rows locked until the surrounding
     * transaction ends. Two Super Admins demoting each other at once would
     * otherwise each count the other and both succeed.
     *
     * @return list<User>
     *
     * @see https://www.doctrine-project.org/projects/doctrine-orm/en/current/reference/transactions-and-concurrency.html#pessimistic-locking
     * @see vendor/doctrine/orm/src/Query.php — setLockMode() refuses a pessimistic lock outside a transaction
     */
    public function findActiveSuperAdminsForUpdate(): array
    {
        /** @var list<User> $superAdmins */
        $superAdmins = $this->createQueryBuilder('u')
            ->andWhere('u.tier = :tier')
            ->andWhere('u.active = true')
            ->setParameter('tier', TierEnum::SuperAdmin)
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getResult();

        return $superAdmins;
    }
}
