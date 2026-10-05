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
 * What a fee at a destination is for.
 */
enum FeeKindEnum: string
{
    case Entry = 'entry';
    case Conservation = 'conservation';
    case Concession = 'concession';
    case Service = 'service';

    public function label(): string
    {
        return match ($this) {
            self::Entry => 'Entry',
            self::Conservation => 'Conservation',
            self::Concession => 'Concession',
            self::Service => 'Service',
        };
    }
}
