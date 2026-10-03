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

use Vivutio\Bundle\IdentityBundle\Entity\User;
use Vivutio\Bundle\IdentityBundle\Exception\InvalidPasswordException;

/**
 * The rules a password somebody chooses must meet, from uhifadhi's settled
 * reset page: at least twelve characters, and not containing their name or
 * their address. (Its third rule, not one of the 10,000 most common
 * passwords, waits for the list to be shipped.).
 */
final readonly class PasswordRulesService
{
    /**
     * @throws InvalidPasswordException
     */
    public function refuseIfBroken(User $account, string $password, string $repeat): void
    {
        if (mb_strlen($password) < User::PASSWORD_MIN_LENGTH) {
            throw new InvalidPasswordException('password', \sprintf('A password must be at least %d characters.', User::PASSWORD_MIN_LENGTH));
        }

        $lower = mb_strtolower($password);
        foreach ([$account->getFirstName(), $account->getLastName()] as $name) {
            if (null !== $name && mb_strlen($name) >= 3 && str_contains($lower, mb_strtolower($name))) {
                throw new InvalidPasswordException('password', 'A password must not contain your name.');
            }
        }

        $local = mb_strtolower(explode('@', (string) $account->getEmail())[0]);
        if (mb_strlen($local) >= 3 && str_contains($lower, $local)) {
            throw new InvalidPasswordException('password', 'A password must not contain your email address.');
        }

        if ($password !== $repeat) {
            throw new InvalidPasswordException('repeat', 'The two passwords do not match.');
        }
    }
}
