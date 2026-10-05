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

namespace Vivutio\Bundle\PartnerBundle\Enum;

/**
 * What a partner is to the organization: one that books its rooms or tours,
 * one that sells them on, or one it books from.
 */
enum PartnerKindEnum: string
{
    case TourOperator = 'tour_operator';
    case TravelAgent = 'travel_agent';
    case Accommodation = 'accommodation';
    case Supplier = 'supplier';

    public function label(): string
    {
        return match ($this) {
            self::TourOperator => 'Tour operator',
            self::TravelAgent => 'Travel agent',
            self::Accommodation => 'Accommodation',
            self::Supplier => 'Other supplier',
        };
    }
}
