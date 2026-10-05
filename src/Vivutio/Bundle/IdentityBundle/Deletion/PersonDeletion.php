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

namespace Vivutio\Bundle\IdentityBundle\Deletion;

use Doctrine\ORM\EntityManagerInterface;
use Vivutio\Bundle\IdentityBundle\Entity\AccountLink;
use Vivutio\Bundle\IdentityBundle\Entity\User;
use Vivutio\Contracts\Deletion\DeletionContributorInterface;
use Vivutio\Contracts\Deletion\DeletionLine;
use Vivutio\Contracts\Deletion\DeletionSubject;

/**
 * A person goes with their account and its sign-in links; what they recorded
 * in a package is that package's to answer for. Deactivating stays the way
 * somebody leaves.
 */
final readonly class PersonDeletion implements DeletionContributorInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function supports(object $record): bool
    {
        return $record instanceof User;
    }

    public function describe(object $record): DeletionSubject
    {
        \assert($record instanceof User);

        return new DeletionSubject('person', (string) $record->getEmail(), $record->getFullName());
    }

    public function whatGoes(object $record): array
    {
        $lines = [new DeletionLine(1, 'person', 'people')];
        $links = $this->entityManager->getRepository(AccountLink::class)->count(['account' => $record]);
        if ($links > 0) {
            $lines[] = new DeletionLine($links, 'sign-in link', 'sign-in links');
        }

        return $lines;
    }

    public function whatStays(object $record): array
    {
        return [];
    }

    public function delete(object $record): void
    {
        $this->entityManager->remove($record);
    }
}
