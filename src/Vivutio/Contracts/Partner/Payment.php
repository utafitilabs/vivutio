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
 * A payment received against a charge: a key no other payment shares, the
 * charge's key, the amount in cents of the charge's currency, the day it was
 * received, how ("Bank transfer") and the payer's reference. A payment struck
 * out is not one.
 */
final readonly class Payment
{
    public function __construct(
        public string $key,
        public string $chargeKey,
        public string $currency,
        public int $amount,
        public \DateTimeImmutable $receivedOn,
        public string $method,
        public string $reference,
    ) {
        if (1 !== preg_match('{^[A-Z]{3}$}D', $currency) || $amount <= 0) {
            throw new \InvalidArgumentException('A payment is a whole number of cents, above nothing, in a three-letter currency: USD.');
        }
    }
}
