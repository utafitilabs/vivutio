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

namespace Vivutio\Contracts\Stay;

use Vivutio\Contracts\Place\PlacedInterface;

/**
 * Guests expected at a place for some nights: a booking at a property, say.
 * What the front desk works from, kept by the package that owns it; the desk
 * keeps only its own record of the stay, by kind and id.
 */
interface StayInterface extends PlacedInterface
{
    /** The kind of stay, as its source declares it: "property_booking". */
    public function getStayKind(): string;

    /** Its id within its kind, the record's uuid. */
    public function getStayId(): string;

    /** What everybody calls it: "VLL-0001". */
    public function getReference(): string;

    /** Who it is for. */
    public function getGuest(): string;

    /** The first night. */
    public function getArrival(): \DateTimeImmutable;

    /** The morning they leave. */
    public function getDeparture(): \DateTimeImmutable;

    /**
     * The units it takes, by kind: so many Tented Rooms.
     *
     * @return list<array{type: string, name: string, count: int}> type is the kind's id, name what it is called
     */
    public function getUnits(): array;

    /** Whether the guests are expected: a stay held or cancelled is not. */
    public function isExpected(): bool;
}
