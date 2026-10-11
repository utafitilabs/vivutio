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

use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Vivutio\Bundle\IdentityBundle\Entity\AuditEvent;
use Vivutio\Bundle\IdentityBundle\Entity\User;
use Vivutio\Bundle\IdentityBundle\Enum\AuditKindEnum;

/**
 * The audit trail's lines, as ruled (11 October 2026): what was done, by
 * whom and from where. Who is the person signed in, or the installation when
 * nobody is (a command, a migration); where is the request's address.
 */
final readonly class AuditTrail
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
        private RequestStack $requests,
        private ClockInterface $clock,
    ) {
    }

    /**
     * A line, made now, by whoever acts now; it is persisted, not flushed.
     * What the line is about, the person, seat, department or address, is
     * written between « and », and is read in bold.
     */
    public function line(AuditKindEnum $kind, string $said, ?User $actor = null): AuditEvent
    {
        $actor ??= $this->actor();
        preg_match_all('/«([^»]*)»/u', $said, $named);

        return new AuditEvent($this->clock->now(), $kind, str_replace(['«', '»'], '', $said), array_values(array_unique($named[1])), $actor?->getFullName(), null === $actor ? null : (string) $actor->getUuid(), $this->requests->getMainRequest()?->getClientIp());
    }

    /** A line written at once, for what no flush carries: a sign-in. */
    public function record(AuditKindEnum $kind, string $said, ?User $actor = null): void
    {
        $this->entityManager->persist($this->line($kind, $said, $actor));
        $this->entityManager->flush();
    }

    private function actor(): ?User
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $user : null;
    }
}
