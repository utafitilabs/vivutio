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

namespace Vivutio\Bundle\PlaceBundle\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Vivutio\Bundle\PlaceBundle\Service\NightCostService;
use Vivutio\Contracts\Stay\NightCost;
use Vivutio\Contracts\Stay\NightCostSourceInterface;

/**
 * What a night at a place costs a person, asked of the package that keeps the
 * place by the kind and id a module stored ("property:01a…"): a module that
 * prices a tour names no other module.
 */
final class NightCostServiceTest extends TestCase
{
    public function testANightIsCostedByTheSourceOfItsKind(): void
    {
        $costs = new NightCostService([self::source('property', ['lodge' => 9500]), self::source('partner', ['camp' => 28000])]);
        $night = new \DateTimeImmutable('2027-08-04');

        $cost = $costs->costOf('partner:camp', $night);
        self::assertInstanceOf(NightCost::class, $cost);
        self::assertSame(['USD', 28000, 'Tented Room, full board, sharing'], [$cost->currency, $cost->each, $cost->basis]);
        self::assertSame(9500, $costs->costOf('property:lodge', $night)?->each);
    }

    public function testANightNobodyCostsHasNoCost(): void
    {
        $costs = new NightCostService([self::source('property', ['lodge' => 9500])]);
        $night = new \DateTimeImmutable('2027-08-04');

        self::assertNull($costs->costOf('property:elsewhere', $night));
        self::assertNull($costs->costOf('office:hq', $night));
        self::assertNull($costs->costOf('not a stay', $night));
    }

    /**
     * @param array<string, int> $each a person a night sharing, in cents, by place id
     */
    private static function source(string $kind, array $each): NightCostSourceInterface
    {
        return new readonly class($kind, $each) implements NightCostSourceInterface {
            /**
             * @param array<string, int> $each
             */
            public function __construct(private string $kind, private array $each)
            {
            }

            public function kind(): string
            {
                return $this->kind;
            }

            public function cost(string $id, \DateTimeImmutable $night): ?NightCost
            {
                return isset($this->each[$id]) ? new NightCost('USD', $this->each[$id], 'Tented Room, full board, sharing') : null;
            }
        };
    }
}
