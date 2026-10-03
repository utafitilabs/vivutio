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

namespace Vivutio\Bundle\IdentityBundle\Enum;

/**
 * What a link sent to somebody's address lets them do, and for how long.
 */
enum LinkPurposeEnum: string
{
    /** Set a new password: one hour, as uhifadhi's settled reset page says and Symfony recommends. */
    case Reset = 'reset';

    /** Accept an invitation and choose a password: a week, so a link sent on a Friday survives the weekend. */
    case Invitation = 'invitation';

    public function lifetime(): \DateInterval
    {
        return match ($this) {
            self::Reset => new \DateInterval('PT1H'),
            self::Invitation => new \DateInterval('P7D'),
        };
    }
}
