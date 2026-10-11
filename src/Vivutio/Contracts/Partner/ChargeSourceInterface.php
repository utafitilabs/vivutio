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
 * How a package says what the organization is owed for what it keeps: a tour
 * booking's price, a ticket's. The core gathers them for whoever records
 * payments or keeps the books.
 *
 * A reusable bundle is not autoconfigured, so the tag goes on by hand.
 */
interface ChargeSourceInterface
{
    public const string TAG = 'vivutio.charge';

    /**
     * Everything it charges for now; a cancelled thing charges what its
     * cancellation kept, or nothing.
     *
     * @return iterable<Charge>
     */
    public function charges(): iterable;
}
