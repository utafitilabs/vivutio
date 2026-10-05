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
use Vivutio\Bundle\IdentityBundle\Entity\User;
use Vivutio\Contracts\Deletion\DeletionContributorInterface;
use Vivutio\Contracts\Deletion\DeletionLine;
use Vivutio\Contracts\Deletion\DeletionSubject;

/**
 * A department goes; its members keep their account and belong to no
 * department, so they reach no module until one is chosen.
 */
final readonly class DepartmentDeletion implements DeletionContributorInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function supports(object $record): bool
    {
        return $record instanceof Department;
    }

    public function describe(object $record): DeletionSubject
    {
        \assert($record instanceof Department);

        $name = $record->getName();

        return new DeletionSubject('department', $name, $name);
    }

    public function whatGoes(object $record): array
    {
        return [new DeletionLine(1, 'department', 'departments')];
    }

    public function whatStays(object $record): array
    {
        \assert($record instanceof Department);
        $members = \count($this->members($record));

        return 0 === $members ? [] : [new DeletionLine($members, 'person in it, who then belongs to no department', 'people in it, who then belong to no department')];
    }

    public function delete(object $record): void
    {
        \assert($record instanceof Department);
        foreach ($this->members($record) as $member) {
            $member->setDepartment(null);
        }
        $this->entityManager->remove($record);
    }

    /**
     * @return list<User>
     */
    private function members(Department $department): array
    {
        return $this->entityManager->getRepository(User::class)->findBy(['department' => $department]);
    }
}
