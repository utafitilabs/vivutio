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
 * Where a guest lives, as the parks of East Africa charge by it.
 */
enum ResidencyEnum: string
{
    case NonResident = 'non_resident';
    case Resident = 'resident';
    case Citizen = 'citizen';

    public function label(): string
    {
        return match ($this) {
            self::NonResident => 'Non-resident',
            self::Resident => 'Resident',
            self::Citizen => 'Citizen',
        };
    }
}
