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
use Vivutio\Bundle\IdentityBundle\Entity\Department;
use Vivutio\Bundle\IdentityBundle\Entity\Office;
use Vivutio\Bundle\IdentityBundle\Entity\User;
use Vivutio\Contracts\Deletion\DeletionContributorInterface;
use Vivutio\Contracts\Deletion\DeletionLine;
use Vivutio\Contracts\Deletion\DeletionSubject;

/**
 * An office goes; people posted there are posted nowhere, and departments
 * that sat there sit with the organization. Postings and places are kept as
 * a kind and an id, so they are cleared here, not by the database.
 */
final readonly class OfficeDeletion implements DeletionContributorInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function supports(object $record): bool
    {
        return $record instanceof Office;
    }

    public function describe(object $record): DeletionSubject
    {
        \assert($record instanceof Office);

        return new DeletionSubject('office', $record->getName(), $record->getName());
    }

    public function whatGoes(object $record): array
    {
        return [new DeletionLine(1, 'office', 'offices')];
    }

    public function whatStays(object $record): array
    {
        \assert($record instanceof Office);
        $lines = [];
        $posted = \count($this->posted($record));
        if ($posted > 0) {
            $lines[] = new DeletionLine($posted, 'person posted there, who is then posted nowhere', 'people posted there, who are then posted nowhere');
        }
        $sitting = \count($this->sitting($record));
        if ($sitting > 0) {
            $lines[] = new DeletionLine($sitting, 'department sitting there, which then sits with the organization', 'departments sitting there, which then sit with the organization');
        }

        return $lines;
    }

    public function delete(object $record): void
    {
        \assert($record instanceof Office);
        foreach ($this->posted($record) as $person) {
            $person->setPosting(null, null);
        }
        foreach ($this->sitting($record) as $department) {
            $department->setPlace(null);
        }
        $this->entityManager->remove($record);
    }

    /**
     * @return list<User>
     */
    private function posted(Office $office): array
    {
        return $this->entityManager->getRepository(User::class)->findBy(['postedKind' => Office::PLACE_KIND, 'postedId' => $office->getPlaceId()]);
    }

    /**
     * @return list<Department>
     */
    private function sitting(Office $office): array
    {
        return $this->entityManager->getRepository(Department::class)->findBy(['placeKind' => Office::PLACE_KIND, 'placeId' => $office->getPlaceId()]);
    }
}
