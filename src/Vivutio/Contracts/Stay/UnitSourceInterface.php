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

namespace Vivutio\Contracts\Stay;

use Vivutio\Contracts\Place\PlaceInterface;

/**
 * How a package says what units a place has to put guests in, by kind: twenty
 * Tented Rooms, numbered 1 to 20, so a stay can be given Tented Room 7.
 *
 * A reusable bundle is not autoconfigured, so the tag goes on by hand.
 */
interface UnitSourceInterface
{
    public const string TAG = 'vivutio.stay.units';

    /**
     * The place's kinds of unit and how many of each, or null where it has
     * no say over the place.
     *
     * @return list<array{type: string, name: string, count: int}>|null
     */
    public function units(PlaceInterface $place): ?array;
}
