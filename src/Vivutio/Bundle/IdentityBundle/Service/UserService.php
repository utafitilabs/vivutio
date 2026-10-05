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
use Vivutio\Bundle\IdentityBundle\Entity\Department;
use Vivutio\Bundle\IdentityBundle\Entity\Position;
use Vivutio\Bundle\IdentityBundle\Entity\User;
use Vivutio\Bundle\IdentityBundle\Enum\TierEnum;
use Vivutio\Bundle\IdentityBundle\Exception\EmailAlreadyUsedException;
use Vivutio\Bundle\IdentityBundle\Exception\InvalidDepartmentException;
use Vivutio\Bundle\IdentityBundle\Exception\InvalidPersonException;
use Vivutio\Bundle\IdentityBundle\Exception\InvalidPlaceException;
use Vivutio\Bundle\IdentityBundle\Exception\LastSuperAdminException;
use Vivutio\Bundle\IdentityBundle\Exception\PasswordTooShortException;
use Vivutio\Bundle\IdentityBundle\Repository\DepartmentRepository;
use Vivutio\Bundle\IdentityBundle\Repository\UserRepository;
use Vivutio\Contracts\Place\PlaceInterface;

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
    /** Not a hash any password verifies against: an invited account has none until it is accepted. */
    public const string NO_PASSWORD = '!invited';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $hasher,
        private UserRepository $users,
        private DepartmentRepository $departments,
        private PlaceDirectoryService $places,
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
     * An account for somebody invited by email: the address and nothing else,
     * since they name themselves on accepting. It cannot be signed in to until
     * they choose a password, which no hash here would match.
     *
     * @throws InvalidPersonException    when it is not an address
     * @throws EmailAlreadyUsedException when somebody already signs in with it
     */
    public function invite(string $email, ?Position $position, ?User $invitedBy): User
    {
        $email = mb_strtolower(trim($email));
        if (false === filter_var($email, \FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 180) {
            throw new InvalidPersonException('email', 'That is not an email address.');
        }
        if (null !== $this->users->findOneBy(['email' => $email])) {
            throw new EmailAlreadyUsedException($email);
        }

        $user = (new User())
            ->setEmail($email)
            ->setFirstName('')
            ->setLastName('')
            ->setTier(TierEnum::Staff)
            ->setPosition($position)
            ->setVerified(false)
            ->setInvitation(new \DateTimeImmutable(), $invitedBy)
            ->setPassword(self::NO_PASSWORD);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    /**
     * A person's name and work number. A name is never empty; an empty number
     * is recorded as none.
     *
     * @throws InvalidPersonException
     */
    public function changeDetails(User $account, string $firstName, string $lastName, ?string $phone): void
    {
        $firstName = trim($firstName);
        $lastName = trim($lastName);
        $phone = null === $phone || '' === trim($phone) ? null : trim($phone);

        foreach (['first_name' => [$firstName, 'first name'], 'last_name' => [$lastName, 'last name']] as $field => [$value, $words]) {
            if ('' === $value) {
                throw new InvalidPersonException($field, \sprintf('A %s cannot be empty.', $words));
            }
            if (mb_strlen($value) > User::NAME_MAX_LENGTH) {
                throw new InvalidPersonException($field, \sprintf('A %s can be at most %d characters.', $words, User::NAME_MAX_LENGTH));
            }
        }
        if (null !== $phone && mb_strlen($phone) > User::PHONE_MAX_LENGTH) {
            throw new InvalidPersonException('phone', \sprintf('A number can be at most %d characters.', User::PHONE_MAX_LENGTH));
        }

        $account->setFirstName($firstName)->setLastName($lastName)->setPhone($phone);
        $this->entityManager->flush();
    }

    /**
     * The address somebody signs in with, kept in lowercase so it is one
     * person however it is typed.
     *
     * @throws InvalidPersonException    when it is not an address
     * @throws EmailAlreadyUsedException when somebody else signs in with it
     */
    public function changeEmail(User $account, string $email): void
    {
        $email = mb_strtolower(trim($email));
        if (false === filter_var($email, \FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 180) {
            throw new InvalidPersonException('email', 'That is not an email address.');
        }
        if ($email === $account->getEmail()) {
            return;
        }

        // Asked first: a refused flush closes the entity manager, and the page
        // saying so could then read nothing. The constraint still settles a race.
        if (null !== $this->users->findOneBy(['email' => $email])) {
            throw new EmailAlreadyUsedException($email);
        }

        $account->setEmail($email);

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $clash) {
            throw new EmailAlreadyUsedException($email, $clash);
        }
    }

    /**
     * The seat, or none: a tier holds every permission without one. A seat
     * that heads a department takes one person, who belongs to it.
     *
     * @throws InvalidDepartmentException
     */
    public function changePosition(User $account, ?Position $position): void
    {
        $heads = null === $position ? null : $this->departments->findOneBy(['head' => $position]);
        if (null !== $position && null !== $heads) {
            foreach ($this->users->findBy(['position' => $position]) as $holder) {
                if ($holder->getId() !== $account->getId()) {
                    throw new InvalidDepartmentException('position', \sprintf('%s heads %s and is held by %s; a head is held by one person.', $position->getName(), $heads->getName(), $holder->getFullName()));
                }
            }
            if ($account->getDepartment() !== $heads) {
                throw new InvalidDepartmentException('position', \sprintf('%s heads %s; whoever holds it must belong to %s.', $position->getName(), $heads->getName(), $heads->getName()));
            }
        }

        $account->setPosition($position);
        $this->entityManager->flush();
    }

    /**
     * The one department somebody belongs to, and those they support; their
     * own is never also one they support. Whoever holds a department's head
     * seat stays in that department.
     *
     * @param list<Department> $supports
     *
     * @throws InvalidDepartmentException
     */
    public function changeDepartments(User $account, ?Department $department, array $supports): void
    {
        $seat = $account->getPosition();
        $heads = null === $seat ? null : $this->departments->findOneBy(['head' => $seat]);
        if (null !== $seat && null !== $heads && $heads !== $department) {
            throw new InvalidDepartmentException('department', \sprintf('%s holds %s, which heads %s, so they belong to %s.', $account->getFullName(), $seat->getName(), $heads->getName(), $heads->getName()));
        }

        $supports = array_values(array_filter($supports, static fn (Department $supported): bool => $supported !== $department));
        foreach ([...(null === $department ? [] : [$department]), ...$supports] as $chosen) {
            if (!$chosen->isReachableFrom($account)) {
                throw new InvalidDepartmentException('department', \sprintf('%s sits at %s, and %s is not posted there: the organization\'s departments, or those of the place one is posted at.', $chosen->getName(), $this->places->nameOf($chosen->getPlaceKind(), $chosen->getPlaceId()), $account->getFullName()));
            }
        }

        $account->setDepartment($department)->setSupports($supports);
        $this->entityManager->flush();
    }

    /**
     * Where somebody sits, in one change as their Position card saves it: the
     * place they are posted at, the department they belong to and those they
     * support (the organization's, or the new place's), and their seat (one
     * that heads a department taking one person, who belongs to it).
     *
     * @param list<Department> $supports
     *
     * @throws InvalidDepartmentException
     * @throws InvalidPlaceException
     */
    public function changeSeat(User $account, ?PlaceInterface $place, ?Department $department, array $supports, ?Position $position): void
    {
        if (!$account->isPostedAt($place)) {
            $this->places->assertOffered($place, 'posted_at');
        }

        $supports = array_values(array_filter($supports, static fn (Department $supported): bool => $supported !== $department));
        foreach ([...(null === $department ? [] : [$department]), ...$supports] as $chosen) {
            if (!$chosen->sitsAt(null) && !$chosen->sitsAt($place)) {
                throw new InvalidDepartmentException('department', \sprintf('%s sits at %s, and %s would not be posted there: the organization\'s departments, or those of the place one is posted at.', $chosen->getName(), $this->places->nameOf($chosen->getPlaceKind(), $chosen->getPlaceId()), $account->getFullName()));
            }
        }

        $heads = null === $position ? null : $this->departments->findOneBy(['head' => $position]);
        if (null !== $position && null !== $heads) {
            foreach ($this->users->findBy(['position' => $position]) as $holder) {
                if ($holder->getId() !== $account->getId()) {
                    throw new InvalidDepartmentException('position', \sprintf('%s heads %s and is held by %s; a head is held by one person.', $position->getName(), $heads->getName(), $holder->getFullName()));
                }
            }
            if ($department !== $heads) {
                throw new InvalidDepartmentException('position', \sprintf('%s heads %s; whoever holds it must belong to %s.', $position->getName(), $heads->getName(), $heads->getName()));
            }
        }

        if (!$account->isPostedAt($place)) {
            $account->setPosting($place, null === $place ? null : new \DateTimeImmutable());
        }
        $account->setDepartment($department)->setSupports($supports)->setPosition($position);
        $this->entityManager->flush();
    }

    /**
     * The one place somebody is posted at, an office or a package's, from
     * today, or none. Moving them is refused while they belong to, or
     * support, a department of the place they leave: those are chosen anew
     * with the posting.
     *
     * @throws InvalidDepartmentException
     * @throws InvalidPlaceException
     */
    public function changePosting(User $account, ?PlaceInterface $place): void
    {
        if ($account->isPostedAt($place)) {
            return;
        }
        $this->places->assertOffered($place, 'posted_at');

        foreach ([...(null === $account->getDepartment() ? [] : [$account->getDepartment()]), ...$account->getSupports()] as $held) {
            if (!$held->sitsAt(null) && !$held->sitsAt($place)) {
                throw new InvalidDepartmentException('department', \sprintf('%s belongs to or supports %s, which sits at %s: choose their departments with the new posting.', $account->getFullName(), $held->getName(), $this->places->nameOf($held->getPlaceKind(), $held->getPlaceId())));
            }
        }

        $account->setPosting($place, null === $place ? null : new \DateTimeImmutable());
        $this->entityManager->flush();
    }

    /** Somebody deactivated may sign in again, with everything they had. */
    public function reactivate(User $account): void
    {
        $account->setActive(true);
        $this->entityManager->flush();
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
