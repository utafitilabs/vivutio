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
use Vivutio\Bundle\IdentityBundle\Entity\User;
use Vivutio\Bundle\IdentityBundle\Enum\TierEnum;
use Vivutio\Bundle\IdentityBundle\Model\TierChange;
use Vivutio\Bundle\IdentityBundle\Security\AccountVoter;
use Vivutio\Core\Tests\Application\Kernel;

/**
 * Every question one person asks about another's account, asked of the
 * installation's own access decision manager, with the reason a refusal
 * gives.
 *
 * Each table is written out whole, so a rule nobody wrote down is a missing
 * row rather than a gap somebody finds by using it.
 */
final class WhoActsOnAnAccountTest extends MigrationsTestCase
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

    /**
     * @return iterable<string, array{TierEnum, TierEnum, bool}>
     */
    public static function actingOnAnAccount(): iterable
    {
        foreach (TierEnum::cases() as $actor) {
            foreach (TierEnum::cases() as $target) {
                $allowed = match ($target) {
                    TierEnum::SuperAdmin => TierEnum::SuperAdmin === $actor,
                    TierEnum::Admin => TierEnum::Staff !== $actor,
                    TierEnum::Staff => true,
                };

                yield \sprintf('%s on %s', $actor->label(), $target->label()) => [$actor, $target, $allowed];
            }
        }
    }

    #[DataProvider('actingOnAnAccount')]
    public function testWhoActsOnWhoseAccount(TierEnum $actor, TierEnum $target, bool $allowed): void
    {
        $this->aSecondSuperAdmin();

        $this->assertDecision($allowed, $this->person('actor', $actor), AccountVoter::ACT_ON, $this->person('target', $target));
    }

    /**
     * @return iterable<string, array{TierEnum, TierEnum, TierEnum, bool}>
     */
    public static function changingATier(): iterable
    {
        foreach (TierEnum::cases() as $actor) {
            foreach (TierEnum::cases() as $held) {
                foreach (TierEnum::cases() as $given) {
                    $allowed = match ($actor) {
                        TierEnum::SuperAdmin => true,
                        TierEnum::Admin => TierEnum::SuperAdmin !== $held && TierEnum::SuperAdmin !== $given,
                        TierEnum::Staff => false,
                    };

                    yield \sprintf('%s moves %s to %s', $actor->label(), $held->label(), $given->label()) => [$actor, $held, $given, $allowed];
                }
            }
        }
    }

    /**
     * Another active Super Admin is always present here, so these rows are
     * the tiers' rule alone; the last one is its own test.
     */
    #[DataProvider('changingATier')]
    public function testWhoMovesWhichTierToWhich(TierEnum $actor, TierEnum $held, TierEnum $given, bool $allowed): void
    {
        $this->aSecondSuperAdmin();

        $this->assertDecision(
            $allowed,
            $this->person('actor', $actor),
            AccountVoter::CHANGE_TIER,
            new TierChange($this->person('target', $held), $given),
        );
    }

    public function testNobodyRaisesThemselvesAboveTheirOwnTier(): void
    {
        $this->aSecondSuperAdmin();

        $admin = $this->person('admin', TierEnum::Admin);
        $staff = $this->person('staff', TierEnum::Staff);

        $this->assertDecision(false, $admin, AccountVoter::CHANGE_TIER, new TierChange($admin, TierEnum::SuperAdmin));
        $this->assertDecision(false, $staff, AccountVoter::CHANGE_TIER, new TierChange($staff, TierEnum::Admin));
    }

    /**
     * @return iterable<string, array{TierEnum, TierEnum, bool}>
     */
    public static function deactivatingAnAccount(): iterable
    {
        yield from self::actingOnAnAccount();
    }

    #[DataProvider('deactivatingAnAccount')]
    public function testWhoDeactivatesWhom(TierEnum $actor, TierEnum $target, bool $allowed): void
    {
        $this->aSecondSuperAdmin();

        $this->assertDecision($allowed, $this->person('actor', $actor), AccountVoter::DEACTIVATE, $this->person('target', $target));
    }

    public function testTheLastActiveSuperAdminIsNeitherDemotedNorDeactivatedAndSaysWhy(): void
    {
        $last = $this->person('last', TierEnum::SuperAdmin);
        $this->person('gone', TierEnum::SuperAdmin, active: false);

        foreach ([TierEnum::Admin, TierEnum::Staff] as $lower) {
            $decision = $this->decide($last, AccountVoter::CHANGE_TIER, new TierChange($last, $lower));
            self::assertFalse($decision->isGranted, 'the last active Super Admin is not demoted to '.$lower->label());
            self::assertStringContainsString('only active Super Admin', $decision->getMessage());
        }

        $decision = $this->decide($last, AccountVoter::DEACTIVATE, $last);
        self::assertFalse($decision->isGranted, 'the last active Super Admin is not deactivated');
        self::assertStringContainsString('only active Super Admin', $decision->getMessage());

        $this->assertDecision(true, $last, AccountVoter::CHANGE_TIER, new TierChange($last, TierEnum::SuperAdmin));
    }

    public function testASecondActiveSuperAdminLiftsTheRefusal(): void
    {
        $first = $this->person('first', TierEnum::SuperAdmin);
        $this->aSecondSuperAdmin();

        $this->assertDecision(true, $first, AccountVoter::CHANGE_TIER, new TierChange($first, TierEnum::Admin));
        $this->assertDecision(true, $first, AccountVoter::DEACTIVATE, $first);
    }

    /**
     * @return iterable<string, array{TierEnum, TierEnum, bool}>
     */
    public static function signingInAsAnother(): iterable
    {
        foreach (TierEnum::cases() as $actor) {
            foreach (TierEnum::cases() as $target) {
                yield \sprintf('%s as %s', $actor->label(), $target->label()) => [$actor, $target, TierEnum::SuperAdmin === $actor];
            }
        }
    }

    #[DataProvider('signingInAsAnother')]
    public function testOnlyASuperAdminSignsInAsAnotherPerson(TierEnum $actor, TierEnum $target, bool $allowed): void
    {
        $this->assertDecision($allowed, $this->person('actor', $actor), AccountVoter::SIGN_IN_AS, $this->person('target', $target));
    }

    /**
     * A subject the voter cannot read is refused, not waved through: the
     * attribute is the voter's, so nobody else answers it either.
     */
    public function testAQuestionAboutSomethingThatIsNotAnAccountIsRefused(): void
    {
        $admin = $this->person('admin', TierEnum::Admin);

        foreach ([AccountVoter::ACT_ON, AccountVoter::DEACTIVATE, AccountVoter::SIGN_IN_AS, AccountVoter::CHANGE_TIER] as $attribute) {
            $this->assertDecision(false, $admin, $attribute, 'not an account');
        }
    }

    private function assertDecision(bool $allowed, User $actor, string $attribute, mixed $subject): void
    {
        $decision = $this->decide($actor, $attribute, $subject);

        self::assertSame($allowed, $decision->isGranted);

        if (!$allowed) {
            self::assertNotSame('', $decision->getMessage(), 'a refusal says why');
        }
    }

    private function decide(User $actor, string $attribute, mixed $subject): AccessDecision
    {
        $security = static::getContainer()->get(Kernel::SECURITY);
        self::assertInstanceOf(Security::class, $security);

        return $security->getAccessDecisionForUser($actor, $attribute, $subject);
    }

    private function aSecondSuperAdmin(): void
    {
        $this->person('second', TierEnum::SuperAdmin);
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
