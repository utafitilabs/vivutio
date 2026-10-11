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
 * subject, the request in plain words, and any documents that go with it.
 * The channel decides how it travels.
 */
final readonly class PartnerMessage
{
    /**
     * @param list<PartnerAttachment> $attachments
     */
    public function __construct(
        public string $reference,
        public string $subject,
        public string $body,
        public array $attachments = [],
    ) {
    }
}
