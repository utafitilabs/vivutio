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
use Vivutio\Bundle\IdentityBundle\Entity\Position;
use Vivutio\Bundle\IdentityBundle\Entity\User;
use Vivutio\Bundle\IdentityBundle\Repository\PositionRepository;

/**
 * What the database keeps of a position and of who sits in it.
 */
final class APositionIsStoredTest extends MigrationsTestCase
{
    public function testAPositionIsReadBackWithItsGrants(): void
    {
        $this->migrate();

        $manager = $this->entityManager();
        $manager->persist((new Position())->setName('Reservations Manager')->setGrants(['positions.read', 'personal_details.read']));
        $manager->flush();
        $manager->clear();

        $stored = $this->positions()->findOneBy(['name' => 'Reservations Manager']);

        self::assertInstanceOf(Position::class, $stored);
        self::assertSame(['positions.read', 'personal_details.read'], $stored->getGrants());
        self::assertNotNull($stored->getUuid());
    }

    public function testAPersonIsReadBackInTheirPosition(): void
    {
        $this->migrate();

        $position = (new Position())->setName('Reservations Manager');
        $manager = $this->entityManager();
        $manager->persist($position);
        $manager->persist(self::baraka()->setPosition($position));
        $manager->flush();
        $manager->clear();

        $stored = $manager->getRepository(User::class)->findOneBy(['email' => 'baraka.kimaro@vivutio-camps.example']);

        self::assertInstanceOf(User::class, $stored);
        self::assertSame('Reservations Manager', $stored->getPosition()?->getName());
    }

    public function testRemovingAPositionLeavesThePersonWithNone(): void
    {
        $this->migrate();

        $position = (new Position())->setName('Reservations Manager');
        $manager = $this->entityManager();
        $manager->persist($position);
        $manager->persist(self::baraka()->setPosition($position));
        $manager->flush();

        $this->connection->executeStatement('DELETE FROM identity_position');
        $manager->clear();

        $stored = $manager->getRepository(User::class)->findOneBy(['email' => 'baraka.kimaro@vivutio-camps.example']);

        self::assertInstanceOf(User::class, $stored);
        self::assertNull($stored->getPosition());
    }

    private static function baraka(): User
    {
        return (new User())
            ->setEmail('baraka.kimaro@vivutio-camps.example')
            ->setFirstName('Baraka')
            ->setLastName('Kimaro')
            ->setPassword('a hash, never a password');
    }

    private function entityManager(): EntityManagerInterface
    {
        $manager = static::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $manager);

        return $manager;
    }

    private function positions(): PositionRepository
    {
        $repository = $this->entityManager()->getRepository(Position::class);
        self::assertInstanceOf(PositionRepository::class, $repository);

        return $repository;
    }
}
