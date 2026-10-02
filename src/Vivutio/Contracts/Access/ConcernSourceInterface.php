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

namespace Vivutio\Contracts\Access;

/**
 * How a bundle or a module puts its concerns on the positions page.
 *
 * Whoever enforces a concern declares it, here and nowhere else. The
 * alternative, one list in the middle of the product, ends as a list of
 * everything anybody checks, kept by hand and outliving the code it
 * describes.
 *
 * Declaring grants nobody anything. The matrix gains a group of rows; who
 * ticks them is the organization's business. Installing a module never hands
 * an existing person a new power.
 *
 * A reusable bundle is not autoconfigured, so the tag goes on by hand.
 */
interface ConcernSourceInterface
{
    /** The tag that puts a declaration in the installation's catalogue of concerns. */
    public const string TAG = 'vivutio.access.concerns';

    /**
     * Who is declaring these, in the product's words. It is the caption on
     * the group in the grants matrix, so an administrator reading a row can
     * see which package gave it to them, and which package removing would
     * take it away.
     */
    public function declaredBy(): string;

    /**
     * @return iterable<ConcernInterface>
     */
    public function concerns(): iterable;
}
