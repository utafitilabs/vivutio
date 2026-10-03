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
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Vivutio\Bundle\IdentityBundle\Entity\Position;
use Vivutio\Bundle\IdentityBundle\Entity\User;
use Vivutio\Bundle\IdentityBundle\Enum\TierEnum;
use Vivutio\Core\Tests\Application\Kernel;

/**
 * A pair is held when the tier holds everything, or when the position grants
 * it and nothing else stands in the way. Anything that cannot be answered
 * refuses.
 *
 * "notices" belongs to no module, as a core bundle's concern does; "notes"
 * belongs to the notes module, and a module's concern also asks for a
 * department.
 */
final class WhatAPositionGrantsTest extends MigrationsTestCase
{
    private EntityManagerInterface $em;

    private int $people = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate();

        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $this->em = $em;
    }

    /**
     * @return iterable<string, array{TierEnum}>
     */
    public static function theTiersAboveTheMatrix(): iterable
    {
        yield 'Super Admin' => [TierEnum::SuperAdmin];
        yield 'Admin' => [TierEnum::Admin];
    }

    #[DataProvider('theTiersAboveTheMatrix')]
    public function testTheTiersAboveTheMatrixHoldEveryDeclaredPairWithoutAPosition(TierEnum $tier): void
    {
        $person = $this->person($tier);

        foreach (['notices.read', 'notices.record', 'notices.configure', 'notes.read', 'notes.record'] as $pair) {
            self::assertTrue($this->decide($person, $pair)->isGranted, $tier->label().' holds '.$pair);
        }
    }

    public function testStaffHoldWhatTheirPositionGrantsAndNothingElse(): void
    {
        $clerk = $this->person(TierEnum::Staff, $this->position('Front office clerk', ['notices.read']));

        self::assertTrue($this->decide($clerk, 'notices.read')->isGranted);

        $refused = $this->decide($clerk, 'notices.record');
        self::assertFalse($refused->isGranted);
        self::assertStringContainsString('Front office clerk', $refused->getMessage());
    }

    public function testStaffWithNoPositionHoldNothingAndAreToldWhy(): void
    {
        $refused = $this->decide($this->person(TierEnum::Staff), 'notices.read');

        self::assertFalse($refused->isGranted);
        self::assertStringContainsString('no position', $refused->getMessage());
    }

    /**
     * A pair only the tiers hold is refused to a position that still stores
     * it, so no seat can raise itself or hand on more than it holds.
     */
    public function testAPairOnlyTheTiersHoldIsNotHonouredOnAPosition(): void
    {
        $editor = $this->person(TierEnum::Staff, $this->position('Editor', ['notices.read', 'notices.configure']));

        $refused = $this->decide($editor, 'notices.configure');

        self::assertFalse($refused->isGranted);
        self::assertStringContainsString('Only Admins and Super Admins', $refused->getMessage());
    }

    /**
     * Everyone but the tiers belongs to exactly one department, and without
     * one a person reaches no module. No department exists yet, so a module's
     * pair is refused to Staff whatever their position grants.
     */
    public function testAModulesPairIsRefusedToStaffWithNoDepartment(): void
    {
        $writer = $this->person(TierEnum::Staff, $this->position('Writer', ['notes.read', 'notes.record']));

        $refused = $this->decide($writer, 'notes.read');

        self::assertFalse($refused->isGranted);
        self::assertStringContainsString('department', $refused->getMessage());
    }

    public function testADeactivatedAccountHoldsNothing(): void
    {
        foreach ([TierEnum::SuperAdmin, TierEnum::Staff] as $tier) {
            $gone = $this->person($tier, $this->position('Clerk '.$tier->value, ['notices.read']), active: false);

            $refused = $this->decide($gone, 'notices.read');
            self::assertFalse($refused->isGranted, 'a deactivated '.$tier->label().' holds nothing');
            self::assertStringContainsString('deactivated', $refused->getMessage());
        }
    }

    /**
     * A pair nothing declares is refused to everybody, the tiers included: a
     * gate on it is a typo or a removed package, and neither should open.
     */
    #[DataProvider('pairsNothingDeclares')]
    public function testAPairNothingDeclaresIsRefusedToEverybody(string $pair): void
    {
        $person = $this->person(TierEnum::SuperAdmin);

        self::assertFalse($this->decide($person, $pair)->isGranted);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function pairsNothingDeclares(): iterable
    {
        yield 'a concern nobody declares' => ['bookings.read'];
        yield 'a verb the concern does not support' => ['notices.delete'];
        yield 'not a pair at all' => ['notices'];
    }

    /**
     * @param list<string> $grants
     */
    private function position(string $name, array $grants): Position
    {
        $position = (new Position())->setName($name)->setGrants($grants);
        $this->em->persist($position);

        return $position;
    }

    private function person(TierEnum $tier, ?Position $position = null, bool $active = true): User
    {
        ++$this->people;

        $user = (new User())
            ->setEmail('person'.$this->people.'@vivutio-camps.example')
            ->setFirstName('Person')
            ->setLastName((string) $this->people)
            ->setTier($tier)
            ->setActive($active)
            ->setPosition($position)
            ->setPassword('a hash, never a password');

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function decide(User $actor, string $pair): AccessDecision
    {
        $security = static::getContainer()->get(Kernel::SECURITY);
        self::assertInstanceOf(Security::class, $security);

        return $security->getAccessDecisionForUser($actor, $pair);
    }
}
