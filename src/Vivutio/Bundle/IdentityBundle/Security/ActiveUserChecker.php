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

namespace Vivutio\Bundle\IdentityBundle\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Vivutio\Bundle\IdentityBundle\Entity\User;

/**
 * A deactivated account is refused at the door, with the reason.
 *
 * It is asked before the password, so the refusal is the same whether or not
 * the password was right: a deactivated account learns nothing new by being
 * tried. The installation names it on its firewall, as `user_checker`.
 *
 * @see https://symfony.com/doc/current/security/user_checkers.html
 * @see vendor/symfony/security-core/User/InMemoryUserChecker.php — the framework's own checker, refusing a disabled account in checkPreAuth()
 */
final class ActiveUserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if ($user instanceof User && !$user->isActive()) {
            throw new CustomUserMessageAccountStatusException('This account has been deactivated and cannot sign in. Ask an administrator to bring it back: nothing has been deleted.');
        }
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
    }
}
