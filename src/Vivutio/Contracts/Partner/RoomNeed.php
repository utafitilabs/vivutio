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
 * Rooms a package needs at a partner's lodge: for whom and how many, from a
 * day for some nights, under a reference the organization reads ("NC-0001 ·
 * Days 3–4"), and a key no other need shares ("tour_booking:01a…:3"), by
 * which a request answering it remembers it.
 */
final readonly class RoomNeed
{
    public function __construct(
        public string $key,
        public string $partnerId,
        public string $reference,
        public string $party,
        public int $people,
        public \DateTimeImmutable $arrival,
        public int $nights,
        public string $about,
        public string $notes,
    ) {
    }
}
