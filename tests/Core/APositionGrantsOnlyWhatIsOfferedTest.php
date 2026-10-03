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
use Vivutio\Bundle\IdentityBundle\Entity\Position;
use Vivutio\Bundle\IdentityBundle\Exception\UngrantablePairsException;
use Vivutio\Bundle\IdentityBundle\Service\PositionService;
use Vivutio\Core\Tests\Application\Kernel;

/**
 * A position is written with the pairs the matrix offers and nothing else. A
 * pair nothing declares would be a box that means nothing, and a pair only the
 * tiers hold would let a seat confer power over people; both are refused
 * before anything is written, whatever sent them.
 */
final class APositionGrantsOnlyWhatIsOfferedTest extends MigrationsTestCase
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

    public function testAPositionIsCreatedWithThePairsTheMatrixOffers(): void
    {
        $position = $this->positions()->create('Front office clerk', ['notices.read', 'notices.record', 'notes.read', 'notices.read']);

        self::assertSame(['notices.read', 'notices.record', 'notes.read'], $this->reread($position)->getGrants());
    }

    public function testAPositionsGrantsAreReplacedWithWhatIsOffered(): void
    {
        $position = $this->positions()->create('Front office clerk', ['notices.read']);

        $this->positions()->changeGrants($position, ['notes.read', 'notes.record']);

        self::assertSame(['notes.read', 'notes.record'], $this->reread($position)->getGrants());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function pairsNoPositionMayCarry(): iterable
    {
        yield 'a pair only the tiers hold' => ['notices.configure', 'only Admins and Super Admins'];
        yield 'a concern nobody declares' => ['bookings.read', 'nothing in this installation declares'];
        yield 'a verb the concern does not support' => ['notices.delete', 'nothing in this installation declares'];
        yield 'not a pair at all' => ['notices', 'not a pair'];
    }

    #[DataProvider('pairsNoPositionMayCarry')]
    public function testAChangeNamingAPairNoPositionMayCarryIsRefusedAndNothingIsWritten(string $pair, string $why): void
    {
        $position = $this->positions()->create('Front office clerk', ['notices.read']);

        try {
            $this->positions()->changeGrants($position, ['notices.record', $pair]);
            self::fail(\sprintf('a position was given "%s"', $pair));
        } catch (UngrantablePairsException $refusal) {
            self::assertSame([$pair], $refusal->pairs);
            self::assertStringContainsString($pair, $refusal->getMessage());
            self::assertStringContainsString($why, $refusal->getMessage());
        }

        self::assertSame(['notices.read'], $this->reread($position)->getGrants());
    }

    #[DataProvider('pairsNoPositionMayCarry')]
    public function testAPositionNamingAPairNoPositionMayCarryIsNotCreated(string $pair, string $why): void
    {
        try {
            $this->positions()->create('Front office clerk', [$pair]);
            self::fail(\sprintf('a position was created with "%s"', $pair));
        } catch (UngrantablePairsException $refusal) {
            self::assertStringContainsString($why, $refusal->getMessage());
        }

        self::assertSame(0, $this->em->getRepository(Position::class)->count([]));
    }

    public function testEveryRefusedPairIsNamedAtOnce(): void
    {
        $this->expectException(UngrantablePairsException::class);
        $this->expectExceptionMessageMatches('/notices\.configure.*bookings\.read/s');

        $this->positions()->create('Front office clerk', ['notices.read', 'notices.configure', 'bookings.read']);
    }

    private function positions(): PositionService
    {
        $positions = static::getContainer()->get(Kernel::POSITIONS);
        self::assertInstanceOf(PositionService::class, $positions);

        return $positions;
    }

    private function reread(Position $position): Position
    {
        $this->em->clear();
        $stored = $this->em->find(Position::class, $position->getId());
        self::assertInstanceOf(Position::class, $stored);

        return $stored;
    }
}
