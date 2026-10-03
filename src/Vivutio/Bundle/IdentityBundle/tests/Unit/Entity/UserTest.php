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

    /**
     * Whether the account a session holds is still the one in the database.
     * Anything that changes what its holder may do ends the session.
     */
    #[DataProvider('changesThatEndASession')]
    public function testAChangeToWhatTheHolderMayDoMakesItAnotherAccount(\Closure $change): void
    {
        $inTheSession = self::neema();
        $inTheDatabase = self::neema();
        $change($inTheDatabase);

        self::assertFalse($inTheSession->isEqualTo($inTheDatabase));
    }

    /**
     * @return iterable<string, array{\Closure(User): User}>
     */
    public static function changesThatEndASession(): iterable
    {
        yield 'deactivated' => [static fn (User $user) => $user->setActive(false)];
        yield 'given another tier' => [static fn (User $user) => $user->setTier(TierEnum::Admin)];
        yield 'given another password' => [static fn (User $user) => $user->setPassword('another hash')];
        yield 'given another address' => [static fn (User $user) => $user->setEmail('neema@elsewhere.example')];
    }

    public function testAnUnchangedAccountIsTheSameAccount(): void
    {
        self::assertTrue(self::neema()->isEqualTo(self::neema()));
    }

    /**
     * A session store is a file or a cache an installation may not guard as
     * closely as its database. It holds a checksum of the password hash, which
     * cannot be cracked back into a password, and never the hash itself.
     */
    public function testTheSessionHoldsAChecksumOfThePasswordAndNeverItsHash(): void
    {
        $hash = '$2y$13$an.example.bcrypt.hash.of.a.long.passphrase.......';
        $stored = serialize(self::neema()->setPassword($hash));

        self::assertStringNotContainsString($hash, $stored);
        self::assertStringContainsString(hash('crc32c', $hash), $stored);
    }

    public function testAnAccountReadBackFromTheSessionIsStillTheSameAccount(): void
    {
        $inTheDatabase = self::neema();
        $inTheSession = unserialize(serialize(self::neema()));
        self::assertInstanceOf(User::class, $inTheSession);

        self::assertTrue($inTheSession->isEqualTo($inTheDatabase));
    }

    public function testAPasswordChangedSinceTheSessionBeganMakesItAnotherAccount(): void
    {
        $inTheSession = unserialize(serialize(self::neema()));
        self::assertInstanceOf(User::class, $inTheSession);

        self::assertFalse($inTheSession->isEqualTo(self::neema()->setPassword('another hash')));
    }

    private static function neema(): User
    {
        return (new User())
            ->setEmail('neema.mollel@vivutio-camps.example')
            ->setFirstName('Neema')
            ->setLastName('Mollel')
            ->setTier(TierEnum::Staff)
            ->setPassword('a hash');
    }
}
