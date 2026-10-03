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

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Vivutio\Bundle\IdentityBundle\Entity\User;
use Vivutio\Bundle\IdentityBundle\Enum\TierEnum;
use Vivutio\Bundle\IdentityBundle\Exception\EmailAlreadyUsedException;
use Vivutio\Bundle\IdentityBundle\Exception\LastSuperAdminException;
use Vivutio\Bundle\IdentityBundle\Exception\PasswordTooShortException;
use Vivutio\Bundle\IdentityBundle\Repository\UserRepository;

/**
 * Every way an account comes into being or changes.
 *
 * Making an account is one set of rules, held here and called by everything
 * that adds somebody. A caller with a hasher and an entity manager of its own
 * would be a second copy of them, drifting from the first the day either
 * changed.
 */
final readonly class UserService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $hasher,
        private UserRepository $users,
    ) {
    }

    /**
     * An account that can sign in at once: active, with a password of its own.
     *
     * @throws PasswordTooShortException when the password is shorter than an account may have
     * @throws EmailAlreadyUsedException when an account already answers to the address
     */
    public function create(
        string $email,
        string $firstName,
        string $lastName,
        #[\SensitiveParameter] string $password,
        TierEnum $tier = TierEnum::Staff,
    ): User {
        if (mb_strlen($password) < User::PASSWORD_MIN_LENGTH) {
            throw new PasswordTooShortException();
        }

        $email = mb_strtolower(trim($email));

        $user = (new User())
            ->setEmail($email)
            ->setFirstName(trim($firstName))
            ->setLastName(trim($lastName))
            ->setTier($tier);
        $user->setPassword($this->hasher->hashPassword($user, $password));

        $this->entityManager->persist($user);

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $clash) {
            throw new EmailAlreadyUsedException($email, $clash);
        }

        return $user;
    }

    /**
     * Whether this account is the only active Super Admin, so that a screen
     * can give the reason in place of a control instead of after it is used.
     */
    public function isLastActiveSuperAdmin(User $account): bool
    {
        return TierEnum::SuperAdmin === $account->getTier()
            && $account->isActive()
            && 1 >= $this->users->countActiveSuperAdmins();
    }

    /**
     * Who may change whose tier is the voter's question; this is the one
     * change the data itself refuses, whoever asks.
     *
     * @throws LastSuperAdminException when it would leave no active Super Admin
     */
    public function changeTier(User $account, TierEnum $tier): void
    {
        $this->entityManager->getConnection()->transactional(function () use ($account, $tier): void {
            if (TierEnum::SuperAdmin !== $tier && $this->wouldLeaveNoSuperAdmin($account)) {
                throw LastSuperAdminException::cannotDemote($account->getFullName());
            }

            $account->setTier($tier);
            $this->entityManager->flush();
        });
    }

    /**
     * An account is deactivated, never removed, so that everything its holder
     * recorded keeps its author.
     *
     * @throws LastSuperAdminException when it would leave no active Super Admin
     */
    public function deactivate(User $account): void
    {
        $this->entityManager->getConnection()->transactional(function () use ($account): void {
            if ($this->wouldLeaveNoSuperAdmin($account)) {
                throw LastSuperAdminException::cannotDeactivate($account->getFullName());
            }

            $account->setActive(false);
            $this->entityManager->flush();
        });
    }

    /**
     * Asked inside the transaction that makes the change, with the active
     * Super Admins locked, so that two of them leaving at once cannot each
     * count on the other.
     *
     * The connection's own transaction, not the entity manager's: a refusal
     * thrown inside the entity manager's closes it, and the request that was
     * refused could then write nothing more.
     *
     * @see vendor/doctrine/orm/src/EntityManager.php — wrapInTransaction() closes the manager on failure
     * @see vendor/doctrine/dbal/src/Connection.php — transactional() rolls back and leaves it open
     */
    private function wouldLeaveNoSuperAdmin(User $account): bool
    {
        if (TierEnum::SuperAdmin !== $account->getTier() || !$account->isActive()) {
            return false;
        }

        foreach ($this->users->findActiveSuperAdminsForUpdate() as $superAdmin) {
            if ($superAdmin->getId() !== $account->getId()) {
                return false;
            }
        }

        return true;
    }
}
