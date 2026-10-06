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

namespace Vivutio\Bundle\PartnerBundle\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Vivutio\Bundle\PartnerBundle\Service\RoomNeedDirectory;
use Vivutio\Contracts\Partner\RoomNeed;
use Vivutio\Contracts\Partner\RoomNeedSourceInterface;

/**
 * Rooms packages need at partners' lodges, gathered for whoever requests them:
 * every source's needs, the soonest first, each found again by its key.
 */
final class RoomNeedDirectoryTest extends TestCase
{
    public function testEverySourcesNeedsAreGatheredTheSoonestFirst(): void
    {
        $needs = new RoomNeedDirectory([
            self::source([self::need('tour_booking:a:3', 'NC-0001 · Days 3–4', '2027-08-04')]),
            self::source([self::need('tour_booking:b:1', 'NC-0002 · Day 1', '2027-07-14'), self::need('tour_booking:a:1', 'NC-0001 · Day 1', '2027-08-02')]),
        ]);

        self::assertSame(['NC-0002 · Day 1', 'NC-0001 · Day 1', 'NC-0001 · Days 3–4'], array_map(static fn (RoomNeed $need): string => $need->reference, $needs->all()));
        self::assertSame('NC-0001 · Days 3–4', $needs->find('tour_booking:a:3')?->reference);
        self::assertNull($needs->find('tour_booking:a:9'));
    }

    /**
     * @param list<RoomNeed> $needs
     */
    private static function source(array $needs): RoomNeedSourceInterface
    {
        return new readonly class($needs) implements RoomNeedSourceInterface {
            /**
             * @param list<RoomNeed> $needs
             */
            public function __construct(private array $needs)
            {
            }

            public function needs(): iterable
            {
                return $this->needs;
            }
        };
    }

    private static function need(string $key, string $reference, string $arrival): RoomNeed
    {
        return new RoomNeed($key, 'partner-1', $reference, 'Hansen family', 4, new \DateTimeImmutable($arrival), 1, 'Northern Circuit', '');
    }
}
