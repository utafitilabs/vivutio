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

/**
 * What a night at a place costs a person sharing, in cents of a currency,
 * and on what basis: "Tented Room, full board, sharing".
 */
final readonly class NightCost
{
    public function __construct(
        public string $currency,
        public int $each,
        public string $basis,
    ) {
    }
}
