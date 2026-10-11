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
 * Something the organization is owed for: a booking, a ticket. A key no other
 * charge shares ("tour_booking:01a…"), the reference the organization reads
 * ("NCS-0002"), what kind of thing it is and what it is for, who owes it (a
 * partner by id, or a party paying directly, named), the amount in cents of
 * a three-letter currency, when it was raised and when it falls due, and the
 * route of the record it comes from.
 */
final readonly class Charge
{
    /**
     * @param array<string, string> $routeParameters
     */
    public function __construct(
        public string $key,
        public string $reference,
        public string $kind,
        public string $about,
        public ?string $partnerId,
        public string $party,
        public string $currency,
        public int $amount,
        public \DateTimeImmutable $raisedOn,
        public \DateTimeImmutable $dueOn,
        public ?string $route = null,
        public array $routeParameters = [],
    ) {
        if (1 !== preg_match('{^[A-Z]{3}$}D', $currency) || $amount < 0) {
            throw new \InvalidArgumentException('A charge is a whole number of cents, not below nothing, in a three-letter currency: USD.');
        }
    }
}
