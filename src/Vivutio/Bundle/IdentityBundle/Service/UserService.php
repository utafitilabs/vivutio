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
use Vivutio\Bundle\IdentityBundle\Exception\PasswordTooShortException;

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
}
