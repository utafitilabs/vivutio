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

use Vivutio\Bundle\IdentityBundle\Entity\Position;

/**
 * A position offered as a department's head, and the reason it cannot be,
 * where it cannot.
 */
final readonly class HeadOption
{
    public function __construct(
        public Position $position,
        public string $says,
        public bool $possible,
    ) {
    }
}
