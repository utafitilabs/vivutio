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
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Vivutio\Bundle\IdentityBundle\Entity\DeletionRecord;
use Vivutio\Bundle\IdentityBundle\Entity\User;
use Vivutio\Bundle\IdentityBundle\Enum\TierEnum;
use Vivutio\Bundle\IdentityBundle\Exception\DeletionRefusedException;
use Vivutio\Bundle\IdentityBundle\Model\DeletionPlan;
use Vivutio\Contracts\Deletion\DeletionContributorInterface;

/**
 * A Super Admin deletes what was made by mistake or in tests, as uhifadhi
 * ruled it: the record and what goes with it, counted first, confirmed by
 * typing its reference, removed in one transaction, with one line kept.
 *
 * Whoever holds rows a delete reaches answers for them through the deletion
 * contract: one owns the kind of record, every other clears its rows first.
 * The tier is read from the signed-in account itself, never from a grant.
 */
final readonly class DeletionService
{
    /**
     * @param iterable<DeletionContributorInterface> $contributors
     */
    public function __construct(
        private iterable $contributors,
        private EntityManagerInterface $entityManager,
        private TokenStorageInterface $tokens,
        private ClockInterface $clock,
    ) {
    }

    public function mayDelete(): bool
    {
        return null !== $this->superAdmin();
    }

    /** What deleting the record would take and leave, before anything happens. */
    public function plan(object $record): DeletionPlan
    {
        $subject = null;
        $goes = [];
        $stays = [];
        foreach ($this->supporting($record) as $contributor) {
            $described = $contributor->describe($record);
            if (null === $described) {
                $goes = [...$goes, ...$contributor->whatGoes($record)];
                $stays = [...$stays, ...$contributor->whatStays($record)];
                continue;
            }
            if (null !== $subject) {
                throw new \LogicException(\sprintf('Two packages own the %s being deleted; one kind of record has one owner.', $described->kind));
            }
            $subject = $described;
            $goes = [...$contributor->whatGoes($record), ...$goes];
            $stays = [...$contributor->whatStays($record), ...$stays];
        }

        return new DeletionPlan($subject ?? throw new \LogicException(\sprintf('Nothing owns a %s, so nothing can delete it.', $record::class)), $goes, $stays);
    }

    /**
     * @throws AccessDeniedException    for anybody but a Super Admin
     * @throws DeletionRefusedException when the reference is not typed as it is, or it is the deleter
     */
    public function delete(object $record, string $typed): DeletionRecord
    {
        $actor = $this->superAdmin() ?? throw new AccessDeniedException('Only a Super Admin deletes.');
        if ($record === $actor) {
            throw DeletionRefusedException::yourself();
        }
        $plan = $this->plan($record);
        if ($typed !== $plan->subject->reference) {
            throw DeletionRefusedException::referenceMismatch($plan->subject->reference);
        }

        $line = new DeletionRecord($this->clock->now(), $actor->getFullName(), $actor->getId(), $plan->subject->kind, $plan->subject->reference, $plan->subject->title, $plan->whatGoes());
        $this->entityManager->wrapInTransaction(function () use ($record, $line): void {
            $owner = null;
            foreach ($this->supporting($record) as $contributor) {
                if (null !== $contributor->describe($record)) {
                    $owner = $contributor;
                    continue;
                }
                $contributor->delete($record);
            }
            $owner?->delete($record);
            $this->entityManager->persist($line);
            $this->entityManager->flush();
        });

        return $line;
    }

    /**
     * @return list<DeletionContributorInterface>
     */
    private function supporting(object $record): array
    {
        $found = [];
        foreach ($this->contributors as $contributor) {
            if ($contributor->supports($record)) {
                $found[] = $contributor;
            }
        }

        return $found;
    }

    private function superAdmin(): ?User
    {
        $user = $this->tokens->getToken()?->getUser();

        return $user instanceof User && TierEnum::SuperAdmin === $user->getTier() ? $user : null;
    }
}
