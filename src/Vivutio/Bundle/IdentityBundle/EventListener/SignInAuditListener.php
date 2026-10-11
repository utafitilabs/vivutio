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

namespace Vivutio\Bundle\IdentityBundle\EventListener;

use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Vivutio\Bundle\IdentityBundle\Entity\User;
use Vivutio\Bundle\IdentityBundle\Enum\AuditKindEnum;
use Vivutio\Bundle\IdentityBundle\Service\AuditTrail;

/**
 * Every sign-in into the audit trail: one that succeeds, by the person who
 * signed in; one that fails, naming the address it was tried for and nobody.
 * Listened to on the global dispatcher, which carries every firewall's
 * sign-in events.
 *
 * @see https://symfony.com/doc/current/security.html#security-events
 * @see vendor/symfony/security-bundle/DependencyInjection/Compiler/RegisterGlobalSecurityEventListenersPass.php
 */
final readonly class SignInAuditListener
{
    public function __construct(private AuditTrail $trail)
    {
    }

    public function onSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if ($user instanceof User) {
            $this->trail->record(AuditKindEnum::SignIn, 'Signed in', $user);
        }
    }

    public function onFailure(LoginFailureEvent $event): void
    {
        $tried = $event->getPassport()?->hasBadge(UserBadge::class) ? $event->getPassport()->getBadge(UserBadge::class) : null;
        $address = $tried instanceof UserBadge ? $tried->getUserIdentifier() : $event->getRequest()->getPayload()->getString('_username');
        $this->trail->record(AuditKindEnum::SignIn, \sprintf('A sign-in failed for «%s»', '' === trim($address) ? 'an address left empty' : trim($address)));
    }
}
