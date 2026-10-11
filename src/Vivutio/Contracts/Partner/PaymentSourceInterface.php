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
 * How a package says which payments it keeps against charges, for whoever
 * keeps the books. A payment struck out is not said.
 *
 * A reusable bundle is not autoconfigured, so the tag goes on by hand.
 */
interface PaymentSourceInterface
{
    public const string TAG = 'vivutio.payment';

    /**
     * @return iterable<Payment>
     */
    public function payments(): iterable;
}
