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
 * A place people are posted at and departments sit at: an office of the
 * core's, or a record a package offers, such as a property.
 *
 * The core keeps a posting as the place's kind and its id, so a package's
 * place has no second record in the core.
 */
interface PlaceInterface
{
    /** The kind of place, as its source declares it: "office". */
    public function getPlaceKind(): string;

    /** Its id within its kind, the record's uuid. */
    public function getPlaceId(): string;

    /** What people call it. */
    public function getName(): string;
}
