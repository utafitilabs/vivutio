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

namespace Vivutio\Bundle\PlaceBundle\Service;

use Vivutio\Contracts\Stay\NightCost;
use Vivutio\Contracts\Stay\NightCostSourceInterface;

/**
 * What a night at a place costs a person sharing, by the kind and id a
 * module keeps it as ("partner:01a…"), asked of the package that answers for
 * that kind.
 */
final readonly class NightCostService
{
    /**
     * @param iterable<NightCostSourceInterface> $sources
     */
    public function __construct(private iterable $sources)
    {
    }

    public function costOf(string $stay, \DateTimeImmutable $night): ?NightCost
    {
        [$kind, $id] = array_pad(explode(':', $stay, 2), 2, '');
        foreach ($this->sources as $source) {
            if ($source->kind() === $kind && '' !== $id) {
                return $source->cost($id, $night);
            }
        }

        return null;
    }
}
