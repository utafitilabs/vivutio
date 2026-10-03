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

namespace Vivutio\Core\Tests\Core\Authority;

use PHPUnit\Framework\TestCase;
use Vivutio\Bundle\IdentityBundle\Test\Holdings;

/**
 * The comparison the escalation check makes, on holdings written by hand, so
 * each way of holding more is known to be caught before any route is asked.
 */
final class HoldingsTest extends TestCase
{
    private const int SENDER = 1;

    private const int OTHER = 2;

    public function testNothingChangingIsNoEscalation(): void
    {
        $before = $this->holdings();

        self::assertSame([], $this->holdings()->escalationsSince($before, self::SENDER));
    }

    public function testGainingAPairTheSenderDoesNotHoldIsCaught(): void
    {
        $after = $this->holdings(otherPairs: ['directory.read', 'personal_details.read']);

        self::assertSame(['account 2 gained personal_details.read'], $after->escalationsSince($this->holdings(), self::SENDER));
    }

    /** A sender may pass on what they hold; that is giving, not escalating. */
    public function testGainingAPairTheSenderHoldsIsNot(): void
    {
        $after = $this->holdings(otherPairs: ['directory.read']);

        self::assertSame([], $after->escalationsSince($this->holdings(otherPairs: []), self::SENDER));
    }

    public function testRaisingOnesOwnPairsIsCaught(): void
    {
        $after = $this->holdings(senderPairs: ['directory.read', 'dashboard.read']);

        self::assertSame(['account 1 gained dashboard.read'], $after->escalationsSince($this->holdings(), self::SENDER));
    }

    public function testATierAboveTheSendersIsCaught(): void
    {
        $after = $this->holdings(otherTier: 'admin');

        self::assertSame(['account 2 now holds the tier admin'], $after->escalationsSince($this->holdings(), self::SENDER));
    }

    public function testSigningInAgainIsCaught(): void
    {
        $before = $this->holdings(otherActive: false);

        self::assertSame(['account 2 can sign in again'], $this->holdings()->escalationsSince($before, self::SENDER));
    }

    public function testANewAccountHoldingMoreThanTheSenderIsCaught(): void
    {
        $after = new Holdings($this->holdings()->people + [3 => ['tier' => 'staff', 'active' => true, 'pairs' => ['personal_details.read']]]);

        self::assertSame(['a new account 3 gained personal_details.read'], $after->escalationsSince($this->holdings(), self::SENDER));
    }

    public function testAStrangerCanGrantNothing(): void
    {
        $after = $this->holdings(otherPairs: ['directory.read']);

        self::assertSame(['account 2 gained directory.read'], $after->escalationsSince($this->holdings(otherPairs: []), null));
    }

    /**
     * @param list<string> $senderPairs
     * @param list<string> $otherPairs
     */
    private function holdings(array $senderPairs = ['directory.read'], array $otherPairs = ['directory.read'], string $otherTier = 'staff', bool $otherActive = true): Holdings
    {
        return new Holdings([
            self::SENDER => ['tier' => 'staff', 'active' => true, 'pairs' => $senderPairs],
            self::OTHER => ['tier' => $otherTier, 'active' => $otherActive, 'pairs' => $otherPairs],
        ]);
    }
}
