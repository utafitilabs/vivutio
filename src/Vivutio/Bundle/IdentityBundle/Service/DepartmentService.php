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
use Vivutio\Bundle\IdentityBundle\Access\ConcernCatalogue;
use Vivutio\Bundle\IdentityBundle\Entity\Department;
use Vivutio\Bundle\IdentityBundle\Entity\Office;
use Vivutio\Bundle\IdentityBundle\Entity\Position;
use Vivutio\Bundle\IdentityBundle\Entity\User;
use Vivutio\Bundle\IdentityBundle\Exception\InvalidDepartmentException;
use Vivutio\Bundle\IdentityBundle\Exception\UngrantablePairsException;
use Vivutio\Bundle\IdentityBundle\Repository\DepartmentRepository;
use Vivutio\Bundle\IdentityBundle\Repository\UserRepository;
use Vivutio\Contracts\Access\Grant;

/**
 * Departments: named once, by what they do; allowing a module's pairs and
 * nothing else; headed by a position exactly one person holds, who belongs
 * to the department.
 */
final readonly class DepartmentService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DepartmentRepository $departments,
        private UserRepository $users,
        private ConcernCatalogue $catalogue,
    ) {
    }

    /**
     * A new department allows nothing until it is told what it allows.
     *
     * @throws InvalidDepartmentException
     */
    public function create(string $name, ?Office $office = null): Department
    {
        $department = (new Department())->setOffice($office)->setName($this->name($name, null, $office));

        $this->entityManager->persist($department);
        $this->entityManager->flush();

        return $department;
    }

    /**
     * @throws InvalidDepartmentException
     */
    public function rename(Department $department, string $name): void
    {
        $department->setName($this->name($name, $department, $department->getOffice()));
        $this->entityManager->flush();
    }

    /**
     * Replaces what the department allows. Only a module's pairs: the core's
     * own are a position's alone, and a pair only the tiers hold no department
     * lifts.
     *
     * @param list<string> $pairs
     *
     * @throws UngrantablePairsException
     */
    public function changeAllows(Department $department, array $pairs): void
    {
        $refused = [];
        foreach ($pairs as $pair) {
            $grant = Grant::tryParse($pair);
            $reason = match (true) {
                null === $grant => 'it is not a pair, which is written "<concern>.<verb>"',
                !$this->catalogue->has($grant) => 'nothing in this installation declares it',
                $this->catalogue->isTierOnly($pair) => 'only Admins and Super Admins hold it',
                null === $this->catalogue->moduleOf($grant->concern) => 'it is the core\'s own, which a position alone grants',
                default => null,
            };
            if (null !== $reason) {
                $refused[$pair] = $reason;
            }
        }

        if ([] !== $refused) {
            throw new UngrantablePairsException($refused, 'A department cannot allow these');
        }

        $department->setAllows(array_values(array_unique($pairs)));
        $this->entityManager->flush();
    }

    /**
     * Who heads it: a position held by one person at most, who belongs here,
     * and heading no other department. Null leaves it without a head.
     *
     * @throws InvalidDepartmentException
     */
    public function changeHead(Department $department, ?Position $head): void
    {
        if (null !== $head) {
            $elsewhere = $this->departments->findOneBy(['head' => $head]);
            if (null !== $elsewhere && $elsewhere !== $department) {
                throw new InvalidDepartmentException('head', \sprintf('%s already heads %s; a position heads one department.', $head->getName(), $elsewhere->getName()));
            }

            $holders = $this->users->findBy(['position' => $head]);
            if (\count($holders) > 1) {
                throw new InvalidDepartmentException('head', \sprintf('%s is held by %d people; a head is held by one person.', $head->getName(), \count($holders)));
            }
            foreach ($holders as $holder) {
                if ($holder->getDepartment() !== $department) {
                    throw new InvalidDepartmentException('head', \sprintf('%s holds %s and does not belong to %s; the head must belong to the department.', $holder->getFullName(), $head->getName(), $department->getName()));
                }
            }
        }

        $department->setHead($head);
        $this->entityManager->flush();
    }

    /**
     * Where it sits: the organization's own, or an office. Everybody who
     * belongs to it or supports it must then be posted where it sits.
     *
     * @throws InvalidDepartmentException
     */
    public function moveTo(Department $department, ?Office $office): void
    {
        if ($office === $department->getOffice()) {
            return;
        }

        if (null !== $office) {
            $elsewhere = array_filter(
                $this->users->findAll(),
                static fn (User $user): bool => ($user->getDepartment() === $department || $user->getSupports()->contains($department)) && $user->getPostedAt() !== $office,
            );
            if ([] !== $elsewhere) {
                throw new InvalidDepartmentException('office', \sprintf('%d %s who belong to it or support it %s not posted at %s.', \count($elsewhere), 1 === \count($elsewhere) ? 'person' : 'people', 1 === \count($elsewhere) ? 'is' : 'are', $office->getName()));
            }
        }

        $this->name($department->getName(), $department, $office);
        $department->setOffice($office);
        $this->entityManager->flush();
    }

    /**
     * @throws InvalidDepartmentException
     */
    private function name(string $name, ?Department $renamed, ?Office $office): string
    {
        $name = trim($name);
        if ('' === $name) {
            throw new InvalidDepartmentException('name', 'A department is known by its name: it cannot be empty.');
        }
        if (mb_strlen($name) > Department::NAME_MAX_LENGTH) {
            throw new InvalidDepartmentException('name', \sprintf('A name can be at most %d characters.', Department::NAME_MAX_LENGTH));
        }

        // Once where it sits: two offices may each have a Sales.
        foreach ($this->departments->findBy(['office' => $office]) as $other) {
            if ($other !== $renamed && mb_strtolower($other->getName()) === mb_strtolower($name)) {
                throw new InvalidDepartmentException('name', \sprintf('There is already a department called %s %s.', $other->getName(), null === $office ? 'in the organization' : 'at '.$office->getName()));
            }
        }

        return $name;
    }
}
