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
 * How a package offers its records as places to post people at.
 *
 * One source answers for one kind. Removing the package takes the kind away;
 * a posting at one of its places then reads as a place no package offers,
 * and is chosen anew.
 *
 * A reusable bundle is not autoconfigured, so the tag goes on by hand.
 */
interface PlaceSourceInterface
{
    /** The tag that offers a kind of place to the installation. */
    public const string TAG = 'vivutio.place';

    /** The kind this source answers for, written once: "office". */
    public function kind(): string;

    /** The kind's places as a list heading names them: "Offices". */
    public function label(): string;

    /**
     * The places somebody may be posted at, and a department may sit at, now.
     *
     * @return iterable<PlaceInterface>
     */
    public function places(): iterable;

    /** Any of its places by id, including one no longer offered, so that a posting there is still named. */
    public function find(string $id): ?PlaceInterface;
}
