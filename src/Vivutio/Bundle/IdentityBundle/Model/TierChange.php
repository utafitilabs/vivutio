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

use Vivutio\Bundle\IdentityBundle\Entity\User;
use Vivutio\Bundle\IdentityBundle\Enum\TierEnum;

/**
 * The subject of one question: may this account be given that tier? Whether
 * a change is allowed depends on the tier it would have as much as on the one
 * it holds, so the two travel together.
 */
final readonly class TierChange
{
    public function __construct(
        public User $account,
        public TierEnum $to,
    ) {
    }
}
