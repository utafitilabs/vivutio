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

namespace Vivutio\Contracts\Partner;

/**
 * How a module reaches a partner. The core's manual channel sends the message
 * as an email to the partner's address, and the reply is recorded by hand;
 * the hub channel takes over a partner that is on the hub. A module sends
 * through this and never builds the mail itself.
 */
interface PartnerChannelInterface
{
    /**
     * Sends the message, and says what was done in words a record can keep:
     * "Sent by email to stay@rim-camp.example".
     */
    public function send(PartnerInterface $partner, PartnerMessage $message): string;
}
