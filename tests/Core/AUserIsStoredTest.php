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

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\UuidV7;
use Vivutio\Bundle\IdentityBundle\Entity\User;
use Vivutio\Bundle\IdentityBundle\Enum\TierEnum;
use Vivutio\Bundle\IdentityBundle\Repository\UserRepository;

/**
 * What the database keeps of an account, read back from a database an
 * installation would have: an empty one, migrated.
 */
final class AUserIsStoredTest extends MigrationsTestCase
{
    public function testAnAccountIsReadBackAsItWasWritten(): void
    {
        $this->migrate();

        $manager = $this->entityManager();
        $manager->persist(self::neema()->setTier(TierEnum::Admin)->setActive(false));
        $manager->flush();
        $manager->clear();

        $stored = $this->users()->findOneBy(['email' => 'neema.mollel@vivutio-camps.example']);

        self::assertInstanceOf(User::class, $stored);
        self::assertSame('Neema', $stored->getFirstName());
        self::assertSame('Mollel', $stored->getLastName());
        self::assertSame(TierEnum::Admin, $stored->getTier());
        self::assertFalse($stored->isActive());
        self::assertSame('a hash, never a password', $stored->getPassword());
    }

    public function testAnAccountIsAddressedByAnIdentifierNobodyCanGuess(): void
    {
        $this->migrate();

        $user = self::neema();
        self::assertNull($user->getUuid());

        $manager = $this->entityManager();
        $manager->persist($user);
        $manager->flush();

        self::assertInstanceOf(UuidV7::class, $user->getUuid());
        self::assertNotNull($user->getCreatedAt());
    }

    public function testTwoAccountsCannotShareAnAddress(): void
    {
        $this->migrate();

        $manager = $this->entityManager();
        $manager->persist(self::neema());
        $manager->persist(self::neema()->setFirstName('Another'));

        $this->expectException(UniqueConstraintViolationException::class);

        $manager->flush();
    }

    private static function neema(): User
    {
        return (new User())
            ->setEmail('neema.mollel@vivutio-camps.example')
            ->setFirstName('Neema')
            ->setLastName('Mollel')
            ->setPassword('a hash, never a password');
    }

    private function entityManager(): EntityManagerInterface
    {
        $manager = static::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $manager);

        return $manager;
    }

    private function users(): UserRepository
    {
        $repository = $this->entityManager()->getRepository(User::class);
        self::assertInstanceOf(UserRepository::class, $repository);

        return $repository;
    }
}
