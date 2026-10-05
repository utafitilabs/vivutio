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

namespace Vivutio\Contracts\Place;

/**
 * A record that belongs to a place, so that a permission asked about it is
 * asked about where it is: a booking at a property, a vehicle based at an
 * office. A place is its own place and needs no more than PlaceInterface.
 */
interface PlacedInterface
{
    public function placedAt(): PlaceInterface;
}
