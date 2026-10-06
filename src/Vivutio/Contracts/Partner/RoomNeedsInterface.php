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
 * Every package's room needs, for a module that requests rooms to list and
 * read back by key. The core answers it; a module type-hints this.
 */
interface RoomNeedsInterface
{
    /**
     * Every need, the soonest arrival first.
     *
     * @return list<RoomNeed>
     */
    public function all(): array;

    public function find(string $key): ?RoomNeed;
}
