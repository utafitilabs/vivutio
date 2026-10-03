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

use PHPUnit\Framework\TestCase;
use Vivutio\Bundle\IdentityBundle\Entity\Position;
use Vivutio\Bundle\IdentityBundle\Entity\User;

/**
 * A seat in the organization: a name and the pairs whoever sits in it holds.
 */
final class PositionTest extends TestCase
{
    public function testANewPositionGrantsNothing(): void
    {
        self::assertSame([], (new Position())->getGrants());
    }

    public function testItHoldsThePairsItWasGivenOnceEachInTheOrderGiven(): void
    {
        $position = (new Position())->setGrants(['positions.read', 'personal_details.read', 'positions.read']);

        self::assertSame(['positions.read', 'personal_details.read'], $position->getGrants());
        self::assertTrue($position->grants('personal_details.read'));
        self::assertFalse($position->grants('personal_details.manage'));
    }

    public function testAPersonIsSeatedInOnePositionOrNone(): void
    {
        $position = (new Position())->setName('Reservations Manager');
        $user = new User();

        self::assertNull($user->getPosition());
        self::assertSame($position, $user->setPosition($position)->getPosition());
        self::assertNull($user->setPosition(null)->getPosition());
        self::assertSame('Reservations Manager', (string) $position);
    }
}
