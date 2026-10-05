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

namespace Vivutio\Bundle\IdentityBundle\Model;

use Vivutio\Contracts\Place\PlaceInterface;

/**
 * One kind's places on offer, under the heading a list names them by.
 */
final readonly class PlaceGroup
{
    /**
     * @param list<PlaceInterface> $places
     */
    public function __construct(
        public string $kind,
        public string $label,
        public array $places,
    ) {
    }
}
