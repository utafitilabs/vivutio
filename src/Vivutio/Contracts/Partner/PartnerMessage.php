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
 * What a module sends a partner: the reference both sides will quote, a
 * subject, and the request in plain words. The channel decides how it travels.
 */
final readonly class PartnerMessage
{
    public function __construct(
        public string $reference,
        public string $subject,
        public string $body,
    ) {
    }
}
