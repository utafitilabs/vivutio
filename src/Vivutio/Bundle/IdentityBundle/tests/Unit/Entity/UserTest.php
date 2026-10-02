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

namespace Vivutio\Bundle\IdentityBundle\Tests\Unit\Entity;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Vivutio\Bundle\IdentityBundle\Entity\User;
use Vivutio\Bundle\IdentityBundle\Enum\TierEnum;

/**
 * The account somebody signs in with: who they are, their tier, and whether the
 * account is in use.
 */
final class UserTest extends TestCase
{
    public function testANewAccountIsStaffAndActive(): void
    {
        $user = new User();

        self::assertSame(TierEnum::Staff, $user->getTier());
        self::assertTrue($user->isActive());
    }

    public function testTheAddressIsKeptInLowercaseBecauseItIsWhatSomebodySignsInWith(): void
    {
        $user = (new User())->setEmail('Neema.Mollel@Vivutio-Camps.Example');

        self::assertSame('neema.mollel@vivutio-camps.example', $user->getEmail());
        self::assertSame('neema.mollel@vivutio-camps.example', $user->getUserIdentifier());
    }

    public function testAnAccountWithNoAddressHasNothingToSignInWithAndSaysSo(): void
    {
        $this->expectException(\LogicException::class);

        (new User())->getUserIdentifier();
    }

    public function testTheFullNameIsTheFirstAndTheLast(): void
    {
        $user = (new User())->setFirstName('Neema')->setLastName('Mollel');

        self::assertSame('Neema Mollel', $user->getFullName());
    }

    /**
     * @param list<string> $roles
     */
    #[DataProvider('rolesByTier')]
    public function testATierGivesItsStandingAndAPositionGivesNone(TierEnum $tier, array $roles): void
    {
        self::assertSame($roles, (new User())->setTier($tier)->getRoles());
    }

    /**
     * @return iterable<string, array{TierEnum, list<string>}>
     */
    public static function rolesByTier(): iterable
    {
        yield 'a Super Admin, who alone may sign in as another person' => [TierEnum::SuperAdmin, ['ROLE_USER', 'ROLE_SUPER_ADMIN', 'ROLE_ALLOWED_TO_SWITCH']];
        yield 'an Admin' => [TierEnum::Admin, ['ROLE_USER', 'ROLE_ADMIN']];
        yield 'Staff, whose permissions are asked of their position' => [TierEnum::Staff, ['ROLE_USER']];
    }
}
