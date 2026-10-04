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

namespace Vivutio\Core\Tests\Core;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Vivutio\Bundle\IdentityBundle\Entity\Department;
use Vivutio\Bundle\IdentityBundle\Entity\Position;
use Vivutio\Bundle\IdentityBundle\Entity\User;
use Vivutio\Bundle\IdentityBundle\Enum\TierEnum;
use Vivutio\Bundle\IdentityBundle\Exception\InvalidDepartmentException;
use Vivutio\Bundle\IdentityBundle\Exception\UngrantablePairsException;
use Vivutio\Bundle\IdentityBundle\Service\DepartmentService;
use Vivutio\Bundle\IdentityBundle\Service\UserService;
use Vivutio\Core\Tests\Application\Kernel;

/**
 * Departments, as ruled (vivutio DECISIONS, 2 October 2026, after uhifadhi):
 * everybody below the tiers belongs to exactly one, may support any number,
 * and holds a module's pair only when their position grants it and their
 * department, or one they support, allows it. A new department allows
 * nothing. Its head is a position one person holds, who belongs to it.
 */
final class WhatADepartmentAllowsTest extends MigrationsTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate();
    }

    public function testAModulesPairHoldsWhereTheDepartmentAllowsIt(): void
    {
        $housekeeping = $this->departments()->create('Housekeeping');
        $this->departments()->changeAllows($housekeeping, ['notes.read']);
        $amani = $this->staff('Amani', ['notes.read', 'notes.record'], $housekeeping);

        self::assertTrue($this->may($amani, 'notes.read'));
        self::assertFalse($this->may($amani, 'notes.record'), 'the position grants it, the department does not allow it');
    }

    public function testANewDepartmentAllowsNothing(): void
    {
        $amani = $this->staff('Amani', ['notes.read'], $this->departments()->create('Housekeeping'));

        self::assertFalse($this->may($amani, 'notes.read'));
    }

    public function testWithoutADepartmentNoModuleIsReached(): void
    {
        $amani = $this->staff('Amani', ['notes.read', 'directory.read'], null);

        self::assertFalse($this->may($amani, 'notes.read'));
        self::assertTrue($this->may($amani, 'directory.read'), 'the core\'s own pairs need no department');
    }

    /** Supporting a department grants its modules, without belonging to it. */
    public function testASupportedDepartmentsModulesAreReachedToo(): void
    {
        $housekeeping = $this->departments()->create('Housekeeping');
        $kitchen = $this->departments()->create('Kitchen');
        $this->departments()->changeAllows($kitchen, ['notes.read']);
        $amani = $this->staff('Amani', ['notes.read'], $housekeeping);

        self::assertFalse($this->may($amani, 'notes.read'));

        $this->accounts()->changeDepartments($amani, $housekeeping, [$kitchen]);

        self::assertTrue($this->may($amani, 'notes.read'));
    }

    /** One's own department is never also one supported. */
    public function testOnesOwnDepartmentIsNotAlsoSupported(): void
    {
        $housekeeping = $this->departments()->create('Housekeeping');
        $amani = $this->staff('Amani', [], $housekeeping);

        $this->accounts()->changeDepartments($amani, $housekeeping, [$housekeeping]);

        self::assertSame([], $amani->getSupports()->toArray());
    }

    public function testTheTiersNeedNoDepartment(): void
    {
        $admin = (new User())->setEmail('baraka@vivutio-camps.example')->setFirstName('Baraka')->setLastName('Kimaro')->setTier(TierEnum::Admin)->setPassword('x');
        $this->em()->persist($admin);
        $this->em()->flush();

        self::assertTrue($this->may($admin, 'notes.record'));
    }

    /** A department allows a module's pairs and nothing else: the core's are the position's alone, and a tier's pair no department lifts. */
    public function testADepartmentAllowsOnlyAModulesPairs(): void
    {
        $housekeeping = $this->departments()->create('Housekeeping');

        $this->expectException(UngrantablePairsException::class);
        $this->departments()->changeAllows($housekeeping, ['notes.read', 'directory.read']);
    }

    public function testADepartmentIsNamedOnceAndNeverEmpty(): void
    {
        $this->departments()->create('Housekeeping');

        foreach (['  ', 'housekeeping '] as $name) {
            try {
                $this->departments()->create($name);
                self::fail('"'.$name.'" was accepted');
            } catch (InvalidDepartmentException $refusal) {
                self::assertSame('name', $refusal->field);
            }
        }
    }

    /** The head is a position one person holds, and that person belongs to the department. */
    public function testTheHeadIsAPositionOnePersonHoldsWhoBelongs(): void
    {
        $housekeeping = $this->departments()->create('Housekeeping');
        $kitchen = $this->departments()->create('Kitchen');
        $head = $this->position('Head housekeeper', []);
        $amani = $this->staff('Amani', null, $housekeeping);
        $this->accounts()->changePosition($amani, $head);

        $this->departments()->changeHead($housekeeping, $head);
        self::assertSame('Head housekeeper', $housekeeping->getHead()?->getName());

        $lead = $this->position('Kitchen lead', []);
        $this->accounts()->changePosition($this->staff('Neema', null, $housekeeping), $lead);
        try {
            $this->departments()->changeHead($kitchen, $lead);
            self::fail('a head held by somebody of another department was accepted');
        } catch (InvalidDepartmentException $refusal) {
            self::assertStringContainsString('belong', $refusal->getMessage());
        }

        $two = $this->position('Supervisor', []);
        $this->accounts()->changePosition($this->staff('Elia', null, $housekeeping), $two);
        $this->accounts()->changePosition($this->staff('Jabiri', null, $housekeeping), $two);
        try {
            $this->departments()->changeHead($housekeeping, $two);
            self::fail('a position two people hold was accepted as head');
        } catch (InvalidDepartmentException $refusal) {
            self::assertStringContainsString('one person', $refusal->getMessage());
        }
    }

    /** A head's seat stays one: giving it to a second person, or to somebody of another department, is refused. */
    public function testTheHeadsSeatTakesOnlyOneWhoBelongs(): void
    {
        $housekeeping = $this->departments()->create('Housekeeping');
        $head = $this->position('Head housekeeper', []);
        $this->departments()->changeHead($housekeeping, $head);
        $this->accounts()->changePosition($this->staff('Amani', null, $housekeeping), $head);

        $this->expectException(InvalidDepartmentException::class);
        $this->accounts()->changePosition($this->staff('Elia', null, $housekeeping), $head);
    }

    public function testAHeadsSeatGoesOnlyToSomebodyWhoBelongs(): void
    {
        $housekeeping = $this->departments()->create('Housekeeping');
        $head = $this->position('Head housekeeper', []);
        $this->departments()->changeHead($housekeeping, $head);

        $this->expectException(InvalidDepartmentException::class);
        $this->accounts()->changePosition($this->staff('Elia', null, $this->departments()->create('Kitchen')), $head);
    }

    /**
     * @param list<string>|null $grants
     */
    private function staff(string $name, ?array $grants, ?Department $department): User
    {
        $user = (new User())
            ->setEmail(strtolower($name).'@vivutio-camps.example')
            ->setFirstName($name)
            ->setLastName('Kimaro')
            ->setTier(TierEnum::Staff)
            ->setPosition(null === $grants ? null : $this->position($name.'\'s seat', $grants))
            ->setDepartment($department)
            ->setPassword('x');
        $this->em()->persist($user);
        $this->em()->flush();

        return $user;
    }

    /**
     * @param list<string> $grants
     */
    private function position(string $name, array $grants): Position
    {
        $position = (new Position())->setName($name)->setGrants($grants);
        $this->em()->persist($position);
        $this->em()->flush();

        return $position;
    }

    private function may(User $user, string $pair): bool
    {
        $security = static::getContainer()->get(Kernel::SECURITY);
        self::assertInstanceOf(Security::class, $security);

        return $security->isGrantedForUser($user, $pair);
    }

    private function departments(): DepartmentService
    {
        $service = static::getContainer()->get('test_public.departments');
        self::assertInstanceOf(DepartmentService::class, $service);

        return $service;
    }

    private function accounts(): UserService
    {
        $service = static::getContainer()->get('test_public.accounts');
        self::assertInstanceOf(UserService::class, $service);

        return $service;
    }

    private function em(): EntityManagerInterface
    {
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $em);

        return $em;
    }
}
