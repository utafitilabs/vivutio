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

use Vivutio\Bundle\IdentityBundle\Entity\Department;
use Vivutio\Bundle\IdentityBundle\Entity\User;
use Vivutio\Bundle\IdentityBundle\Model\DepartmentSummary;
use Vivutio\Bundle\IdentityBundle\Model\HeadOption;
use Vivutio\Bundle\IdentityBundle\Repository\DepartmentRepository;
use Vivutio\Bundle\IdentityBundle\Repository\PositionRepository;
use Vivutio\Bundle\IdentityBundle\Repository\UserRepository;

/**
 * The departments as their pages read them: each with who belongs, who
 * supports it and who heads it, and the positions that could head one.
 */
final readonly class DepartmentDirectoryService
{
    public function __construct(
        private DepartmentRepository $departments,
        private UserRepository $users,
        private PositionRepository $positions,
        private PlaceDirectoryService $places,
    ) {
    }

    /**
     * @return list<DepartmentSummary> the organization's, then each place's, by the place's name
     */
    public function summaries(): array
    {
        $summaries = array_map(fn (Department $department): DepartmentSummary => $this->summary($department), $this->departments->findBy([], ['name' => 'ASC']));
        usort($summaries, static fn (DepartmentSummary $a, DepartmentSummary $b): int => [null !== $a->sitsAt, $a->sitsAt, $a->department->getName()] <=> [null !== $b->sitsAt, $b->sitsAt, $b->department->getName()]);

        return $summaries;
    }

    public function summary(Department $department): DepartmentSummary
    {
        $everyone = $this->users->findBy([], ['firstName' => 'ASC', 'lastName' => 'ASC']);
        $head = $department->getHead();

        $members = array_values(array_filter($everyone, static fn (User $user): bool => $user->getDepartment() === $department));
        $holder = null;
        foreach ($members as $member) {
            if (null !== $head && $member->getPosition() === $head) {
                $holder = $member;
            }
        }
        usort($members, static fn (User $a, User $b): int => (int) ($b === $holder) <=> (int) ($a === $holder));

        $supporters = array_values(array_filter($everyone, static fn (User $user): bool => $user->getSupports()->contains($department)));

        return new DepartmentSummary($department, $members, $supporters, $holder, $this->places->nameOf($department->getPlaceKind(), $department->getPlaceId()));
    }

    /**
     * Every position, said as a head of this department would be, and why
     * one cannot be: held by more than one person, held by somebody of
     * another department, or heading another already.
     *
     * @return list<HeadOption>
     */
    public function headOptions(Department $department): array
    {
        $options = [];

        foreach ($this->positions->findBy([], ['name' => 'ASC']) as $position) {
            $holders = $this->users->findBy(['position' => $position]);
            $elsewhere = $this->departments->findOneBy(['head' => $position]);
            $name = (string) $position->getName();

            $options[] = match (true) {
                null !== $elsewhere && $elsewhere !== $department => new HeadOption($position, \sprintf('%s · heads %s', $name, $elsewhere->getName()), false),
                \count($holders) > 1 => new HeadOption($position, \sprintf('%s · held by %d people; a head is one person', $name, \count($holders)), false),
                1 === \count($holders) && $holders[0]->getDepartment() !== $department => new HeadOption($position, \sprintf('%s · held by %s, who belongs to %s', $name, $holders[0]->getFullName(), $holders[0]->getDepartment()?->getName() ?? 'no department'), false),
                1 === \count($holders) => new HeadOption($position, \sprintf('%s · %s', $name, $holders[0]->getFullName()), true),
                default => new HeadOption($position, \sprintf('%s · vacant', $name), true),
            };
        }

        return $options;
    }
}
