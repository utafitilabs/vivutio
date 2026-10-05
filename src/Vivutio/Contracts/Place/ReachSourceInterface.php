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

namespace Vivutio\Contracts\Place;

/**
 * How a package says whether a person's reach covers a place: whether their
 * permissions apply there. The grant voter asks every source after a pair is
 * otherwise held, for a subject that is a place or belongs to one; a single
 * "no" refuses. A source answers null for a place it has no say over, and a
 * place no source has a say over is not narrowed.
 *
 * The tiers are never asked about: their permissions apply everywhere.
 *
 * A reusable bundle is not autoconfigured, so the tag goes on by hand.
 */
interface ReachSourceInterface
{
    public const string TAG = 'vivutio.place.reach';

    /**
     * @param string $person the person's uuid
     */
    public function covers(string $person, PlaceInterface $place): ?bool;
}
