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

namespace Vivutio\Bundle\ShellBundle\EventListener;

use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Environment;
use Twig\Extension\CoreExtension;
use Vivutio\Contracts\Settings\OrganizationIdentitySourceInterface;

/**
 * Every date a page draws is in the organization's time zone, whichever
 * package draws it, set once a request on Twig's core extension. Until the
 * organization is recorded, dates stay in the zone the installation runs in.
 * A page nobody is signed in to shows no dates and is never made to read the
 * organization: the sign-in form opens before anything else is there. It runs
 * after the firewall, which sets who is signed in.
 *
 * @see https://twig.symfony.com/doc/3.x/filters/date.html#timezone
 *      "The default timezone can also be set globally by calling setTimezone()"
 */
final readonly class OrganizationTimeZoneListener
{
    public function __construct(
        private Environment $twig,
        private OrganizationIdentitySourceInterface $organization,
        private TokenStorageInterface $tokens,
    ) {
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || null === $this->tokens->getToken()?->getUser()) {
            return;
        }
        $timeZone = $this->organization->identity()?->timeZone;
        if (null !== $timeZone && \in_array($timeZone, \DateTimeZone::listIdentifiers(), true)) {
            $this->twig->getExtension(CoreExtension::class)->setTimezone($timeZone);
        }
    }
}
