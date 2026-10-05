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

namespace Vivutio\Core\Tests\Application\NotesModule;

use Vivutio\Contracts\Place\PlaceInterface;
use Vivutio\Contracts\Place\ReachSourceInterface;

/**
 * A package's say over its own places, played by the notice boards: a person
 * reaches the boards a specification names for them, and nothing is said of
 * any other kind of place.
 */
final class NotesReach implements ReachSourceInterface
{
    /** @var array<string, list<string>> board ids by person uuid; a person not named reaches every board */
    public static array $covering = [];

    public function covers(string $person, PlaceInterface $place): ?bool
    {
        if (NotesPlaces::KIND !== $place->getPlaceKind()) {
            return null;
        }

        return !\array_key_exists($person, self::$covering) || \in_array($place->getPlaceId(), self::$covering[$person], true);
    }
}
