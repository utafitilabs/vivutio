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

/**
 * How a package says what a night at one of its places costs a person
 * sharing: its own property by its rate card, a partner's lodge by the rates
 * agreed with it. A module that prices a tour asks the core, never the package.
 *
 * A reusable bundle is not autoconfigured, so the tag goes on by hand.
 */
interface NightCostSourceInterface
{
    public const string TAG = 'vivutio.night_cost';

    /** The kind of place it answers for, as a module stores it: "property", "partner". */
    public function kind(): string;

    /** What the night costs a person sharing, or null where no rate covers it. */
    public function cost(string $id, \DateTimeImmutable $night): ?NightCost;
}
