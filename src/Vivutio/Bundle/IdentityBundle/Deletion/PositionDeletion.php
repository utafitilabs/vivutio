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
use Vivutio\Bundle\IdentityBundle\Entity\Position;
use Vivutio\Bundle\IdentityBundle\Entity\User;
use Vivutio\Contracts\Deletion\DeletionContributorInterface;
use Vivutio\Contracts\Deletion\DeletionLine;
use Vivutio\Contracts\Deletion\DeletionSubject;

/**
 * A position goes; the people holding it keep their account without a seat,
 * and a department it heads is left without a head.
 */
final readonly class PositionDeletion implements DeletionContributorInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function supports(object $record): bool
    {
        return $record instanceof Position;
    }

    public function describe(object $record): DeletionSubject
    {
        \assert($record instanceof Position);

        $name = $record->getName() ?? '';

        return new DeletionSubject('position', $name, $name);
    }

    public function whatGoes(object $record): array
    {
        return [new DeletionLine(1, 'position', 'positions')];
    }

    public function whatStays(object $record): array
    {
        \assert($record instanceof Position);
        $lines = [];
        $holders = \count($this->holders($record));
        if ($holders > 0) {
            $lines[] = new DeletionLine($holders, 'person, who keeps their account without a seat', 'people, who keep their account without a seat');
        }
        $headed = \count($this->headed($record));
        if ($headed > 0) {
            $lines[] = new DeletionLine($headed, 'department it heads, which then has no head', 'departments it heads, which then have no head');
        }

        return $lines;
    }

    public function delete(object $record): void
    {
        \assert($record instanceof Position);
        foreach ($this->holders($record) as $holder) {
            $holder->setPosition(null);
        }
        foreach ($this->headed($record) as $department) {
            $department->setHead(null);
        }
        $this->entityManager->remove($record);
    }

    /**
     * @return list<User>
     */
    private function holders(Position $position): array
    {
        return $this->entityManager->getRepository(User::class)->findBy(['position' => $position]);
    }

    /**
     * @return list<Department>
     */
    private function headed(Position $position): array
    {
        return $this->entityManager->getRepository(Department::class)->findBy(['head' => $position]);
    }
}
