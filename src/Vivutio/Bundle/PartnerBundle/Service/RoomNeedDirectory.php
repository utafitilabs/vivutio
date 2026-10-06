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

namespace Vivutio\Bundle\PartnerBundle\Service;

use Vivutio\Contracts\Partner\RoomNeed;
use Vivutio\Contracts\Partner\RoomNeedsInterface;
use Vivutio\Contracts\Partner\RoomNeedSourceInterface;

/**
 * Every package's room needs, gathered from the sources that say them.
 */
final readonly class RoomNeedDirectory implements RoomNeedsInterface
{
    /**
     * @param iterable<RoomNeedSourceInterface> $sources
     */
    public function __construct(private iterable $sources)
    {
    }

    public function all(): array
    {
        $needs = [];
        foreach ($this->sources as $source) {
            foreach ($source->needs() as $need) {
                $needs[] = $need;
            }
        }
        usort($needs, static fn (RoomNeed $a, RoomNeed $b): int => [$a->arrival, $a->reference] <=> [$b->arrival, $b->reference]);

        return $needs;
    }

    public function find(string $key): ?RoomNeed
    {
        foreach ($this->all() as $need) {
            if ($need->key === $key) {
                return $need;
            }
        }

        return null;
    }
}
