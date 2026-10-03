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
use Vivutio\Bundle\IdentityBundle\Entity\User;
use Vivutio\Bundle\IdentityBundle\Enum\TierEnum;
use Vivutio\Bundle\IdentityBundle\Exception\LastSuperAdminException;
use Vivutio\Bundle\IdentityBundle\Service\UserService;

/**
 * An installation always keeps one active Super Admin, whatever path the
 * change takes: the service refuses it before anything is written, so a
 * command or an import cannot do what a screen is refused.
 */
final class TheLastSuperAdminStaysTest extends MigrationsTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate();

        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $this->em = $em;
    }

    public function testTheLastActiveSuperAdminIsNotDemoted(): void
    {
        $last = $this->person('last', TierEnum::SuperAdmin);

        try {
            $this->accounts()->changeTier($last, TierEnum::Admin);
            self::fail('the last active Super Admin was demoted');
        } catch (LastSuperAdminException $refusal) {
            self::assertStringContainsString('Last Kimaro', $refusal->getMessage());
        }

        self::assertSame(TierEnum::SuperAdmin, $this->reread($last)->getTier());
    }

    public function testTheLastActiveSuperAdminIsNotDeactivated(): void
    {
        $last = $this->person('last', TierEnum::SuperAdmin);

        try {
            $this->accounts()->deactivate($last);
            self::fail('the last active Super Admin was deactivated');
        } catch (LastSuperAdminException) {
        }

        self::assertTrue($this->reread($last)->isActive());
    }

    /** Somebody who can no longer sign in cannot fix anything, so they are not a second one. */
    public function testADeactivatedSuperAdminDoesNotCount(): void
    {
        $last = $this->person('last', TierEnum::SuperAdmin);
        $this->person('gone', TierEnum::SuperAdmin, active: false);

        $this->expectException(LastSuperAdminException::class);

        $this->accounts()->changeTier($last, TierEnum::Staff);
    }

    public function testWithASecondActiveSuperAdminBothChangesAreMade(): void
    {
        $first = $this->person('first', TierEnum::SuperAdmin);
        $second = $this->person('second', TierEnum::SuperAdmin);

        $this->accounts()->changeTier($first, TierEnum::Admin);
        self::assertSame(TierEnum::Admin, $this->reread($first)->getTier());

        $this->person('third', TierEnum::SuperAdmin);
        $this->accounts()->deactivate($this->reread($second));
        self::assertFalse($this->reread($second)->isActive());
    }

    public function testAnybodyElseIsDemotedAndDeactivatedFreely(): void
    {
        $this->person('owner', TierEnum::SuperAdmin);
        $admin = $this->person('admin', TierEnum::Admin);

        $this->accounts()->changeTier($admin, TierEnum::Staff);
        $this->accounts()->deactivate($admin);

        $stored = $this->reread($admin);
        self::assertSame(TierEnum::Staff, $stored->getTier());
        self::assertFalse($stored->isActive());
    }

    public function testTheServiceSaysWhetherAnAccountIsTheLastActiveSuperAdmin(): void
    {
        $last = $this->person('last', TierEnum::SuperAdmin);
        $admin = $this->person('admin', TierEnum::Admin);

        self::assertTrue($this->accounts()->isLastActiveSuperAdmin($last));
        self::assertFalse($this->accounts()->isLastActiveSuperAdmin($admin));

        $this->person('second', TierEnum::SuperAdmin);

        self::assertFalse($this->accounts()->isLastActiveSuperAdmin($last));
    }

    private function accounts(): UserService
    {
        $accounts = static::getContainer()->get(UserService::class);
        self::assertInstanceOf(UserService::class, $accounts);

        return $accounts;
    }

    private function reread(User $user): User
    {
        $this->em->clear();
        $stored = $this->em->find(User::class, $user->getId());
        self::assertInstanceOf(User::class, $stored);

        return $stored;
    }

    private function person(string $name, TierEnum $tier, bool $active = true): User
    {
        $user = (new User())
            ->setEmail($name.'@vivutio-camps.example')
            ->setFirstName(ucfirst($name))
            ->setLastName('Kimaro')
            ->setTier($tier)
            ->setActive($active)
            ->setPassword('a hash, never a password');

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }
}
