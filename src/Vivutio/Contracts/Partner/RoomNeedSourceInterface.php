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
 * How a package says which rooms it needs at partners' lodges: a tour
 * booking's nights, say. The core gathers them for whoever requests rooms.
 *
 * A reusable bundle is not autoconfigured, so the tag goes on by hand.
 */
interface RoomNeedSourceInterface
{
    public const string TAG = 'vivutio.room_need';

    /**
     * The rooms it needs now, the soonest first.
     *
     * @return iterable<RoomNeed>
     */
    public function needs(): iterable;
}
