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

namespace Vivutio\Bundle\IdentityBundle\Tests\Unit\Enum;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Vivutio\Bundle\IdentityBundle\Enum\TierEnum;

/**
 * Three tiers, and what each may do to an account of each. Every cell is
 * written out, so a rule that changes shows as a cell that changes.
 */
final class TierEnumTest extends TestCase
{
    public function testThereAreThreeTiersAndOnlyThree(): void
    {
        self::assertSame(
            ['super_admin', 'admin', 'staff'],
            array_map(static fn (TierEnum $tier): string => $tier->value, TierEnum::cases()),
        );
    }

    #[DataProvider('everyTier')]
    public function testEveryTierHasANameAndASentenceSayingWhatItIs(TierEnum $tier): void
    {
        self::assertNotSame('', trim($tier->label()));
        self::assertNotSame('', trim($tier->description()));
    }

    /**
     * @return iterable<string, array{TierEnum}>
     */
    public static function everyTier(): iterable
    {
        foreach (TierEnum::cases() as $tier) {
            yield $tier->value => [$tier];
        }
    }

    public function testTheTwoUpperTiersHoldEveryPermissionAndStaffHoldTheirPosition(): void
    {
        self::assertTrue(TierEnum::SuperAdmin->holdsEveryPermission());
        self::assertTrue(TierEnum::Admin->holdsEveryPermission());
        self::assertFalse(TierEnum::Staff->holdsEveryPermission());
    }

    public function testOnlyASuperAdminSignsInAsAnotherPerson(): void
    {
        self::assertTrue(TierEnum::SuperAdmin->maySignInAsAnother());
        self::assertFalse(TierEnum::Admin->maySignInAsAnother());
        self::assertFalse(TierEnum::Staff->maySignInAsAnother());
    }

    /**
     * Acting on an account is changing its name or address, sending it a
     * reset link, deactivating it or bringing it back.
     */
    #[DataProvider('actingOnAnAccount')]
    public function testWhoActsOnWhoseAccount(TierEnum $actor, TierEnum $target, bool $allowed): void
    {
        self::assertSame($allowed, $actor->mayActOnAccountOf($target));
    }

    /**
     * @return iterable<string, array{TierEnum, TierEnum, bool}>
     */
    public static function actingOnAnAccount(): iterable
    {
        yield 'a Super Admin on a Super Admin' => [TierEnum::SuperAdmin, TierEnum::SuperAdmin, true];
        yield 'a Super Admin on an Admin' => [TierEnum::SuperAdmin, TierEnum::Admin, true];
        yield 'a Super Admin on Staff' => [TierEnum::SuperAdmin, TierEnum::Staff, true];

        yield 'an Admin on a Super Admin' => [TierEnum::Admin, TierEnum::SuperAdmin, false];
        yield 'an Admin on an Admin' => [TierEnum::Admin, TierEnum::Admin, true];
        yield 'an Admin on Staff' => [TierEnum::Admin, TierEnum::Staff, true];

        yield 'Staff on a Super Admin' => [TierEnum::Staff, TierEnum::SuperAdmin, false];
        yield 'Staff on an Admin' => [TierEnum::Staff, TierEnum::Admin, false];
        yield 'Staff on Staff' => [TierEnum::Staff, TierEnum::Staff, false];
    }

    /**
     * Changing a tier: the actor's tier, the tier the account holds, the tier
     * it would be given.
     */
    #[DataProvider('changingATier')]
    public function testWhoChangesWhoseTierToWhat(TierEnum $actor, TierEnum $held, TierEnum $given, bool $allowed): void
    {
        self::assertSame($allowed, $actor->mayChangeTier($held, $given));
    }

    /**
     * @return iterable<string, array{TierEnum, TierEnum, TierEnum, bool}>
     */
    public static function changingATier(): iterable
    {
        // A Super Admin changes any tier to any tier.
        foreach (TierEnum::cases() as $held) {
            foreach (TierEnum::cases() as $given) {
                yield \sprintf('a Super Admin makes a %s a %s', $held->value, $given->value) => [TierEnum::SuperAdmin, $held, $given, true];
            }
        }

        // Admins are peers: they make and unmake Admins, and never reach a Super Admin.
        yield 'an Admin makes Staff an Admin' => [TierEnum::Admin, TierEnum::Staff, TierEnum::Admin, true];
        yield 'an Admin makes an Admin Staff' => [TierEnum::Admin, TierEnum::Admin, TierEnum::Staff, true];
        yield 'an Admin leaves an Admin an Admin' => [TierEnum::Admin, TierEnum::Admin, TierEnum::Admin, true];
        yield 'an Admin leaves Staff as Staff' => [TierEnum::Admin, TierEnum::Staff, TierEnum::Staff, true];
        yield 'an Admin makes Staff a Super Admin' => [TierEnum::Admin, TierEnum::Staff, TierEnum::SuperAdmin, false];
        yield 'an Admin makes an Admin a Super Admin' => [TierEnum::Admin, TierEnum::Admin, TierEnum::SuperAdmin, false];
        yield 'an Admin makes a Super Admin an Admin' => [TierEnum::Admin, TierEnum::SuperAdmin, TierEnum::Admin, false];
        yield 'an Admin makes a Super Admin Staff' => [TierEnum::Admin, TierEnum::SuperAdmin, TierEnum::Staff, false];
        yield 'an Admin leaves a Super Admin a Super Admin' => [TierEnum::Admin, TierEnum::SuperAdmin, TierEnum::SuperAdmin, false];

        // A position never confers a tier: Staff change none, whatever they hold.
        foreach (TierEnum::cases() as $held) {
            foreach (TierEnum::cases() as $given) {
                yield \sprintf('Staff make a %s a %s', $held->value, $given->value) => [TierEnum::Staff, $held, $given, false];
            }
        }
    }
}
