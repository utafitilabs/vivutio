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

use Vivutio\Contracts\Place\PlaceInterface;

/**
 * How a package offers its records as stays: those at a place around some
 * days, and any of them by id.
 *
 * A reusable bundle is not autoconfigured, so the tag goes on by hand.
 */
interface StaySourceInterface
{
    public const string TAG = 'vivutio.stay';

    /** The kind this source answers for. */
    public function kind(): string;

    /**
     * Its stays at a place that sleep any night from the first to the last,
     * or arrive or leave on a day between them.
     *
     * @return iterable<StayInterface>
     */
    public function at(PlaceInterface $place, \DateTimeImmutable $first, \DateTimeImmutable $last): iterable;

    public function find(string $id): ?StayInterface;
}
