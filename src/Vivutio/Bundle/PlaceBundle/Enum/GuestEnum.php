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

namespace Vivutio\Bundle\PlaceBundle\Enum;

/**
 * Who a fee is charged for, as the parks count them.
 */
enum GuestEnum: string
{
    case Adult = 'adult';
    case Child = 'child';

    public function label(): string
    {
        return match ($this) {
            self::Adult => 'Adult',
            self::Child => 'Child',
        };
    }
}
