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
 * A document that goes with a message: the request as a PDF. The channel
 * carries it as it carries the message, an email's attachment by the manual
 * channel.
 */
final readonly class PartnerAttachment
{
    public function __construct(
        public string $filename,
        public string $contentType,
        public string $body,
    ) {
    }
}
