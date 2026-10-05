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
 * How a request reaches a partner. The manual channel is always there: a
 * request leaves as an email and its status is recorded by hand. The hub
 * channel joins it once the hub is switched on and the partner is on it.
 */
enum ChannelEnum: string
{
    case Manual = 'manual';
    case Hub = 'hub';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::Hub => 'Hub',
        };
    }
}
