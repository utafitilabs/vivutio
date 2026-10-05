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
 * What a fee is charged per.
 */
enum FeePerEnum: string
{
    case PersonDay = 'person_day';
    case PersonEntry = 'person_entry';
    case VehicleEntry = 'vehicle_entry';

    public function label(): string
    {
        return match ($this) {
            self::PersonDay => 'a person a day',
            self::PersonEntry => 'a person an entry',
            self::VehicleEntry => 'a vehicle an entry',
        };
    }
}
