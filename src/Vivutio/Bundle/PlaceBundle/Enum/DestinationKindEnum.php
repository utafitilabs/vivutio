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
 * What kind of place a destination is.
 */
enum DestinationKindEnum: string
{
    case NationalPark = 'national_park';
    case GameReserve = 'game_reserve';
    case ConservationArea = 'conservation_area';
    case Conservancy = 'conservancy';
    case MarinePark = 'marine_park';
    case Mountain = 'mountain';
    case Lake = 'lake';
    case Beach = 'beach';
    case City = 'city';
    case Forest = 'forest';

    public function label(): string
    {
        return match ($this) {
            self::NationalPark => 'National park',
            self::GameReserve => 'Game reserve',
            self::ConservationArea => 'Conservation area',
            self::Conservancy => 'Conservancy',
            self::MarinePark => 'Marine park',
            self::Mountain => 'Mountain',
            self::Lake => 'Lake',
            self::Beach => 'Beach',
            self::City => 'City',
            self::Forest => 'Forest',
        };
    }
}
