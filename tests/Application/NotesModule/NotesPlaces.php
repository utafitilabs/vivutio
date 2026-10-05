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
use Vivutio\Contracts\Place\PlaceSourceInterface;

/**
 * Places a package offers of its own, played by invented notice boards: one
 * offered, and one no longer offered that is still named.
 */
final class NotesPlaces implements PlaceSourceInterface
{
    public const string KIND = 'board';
    public const string KITCHEN = '0199b0a0-0000-7000-8000-00000000b001';
    public const string RETIRED = '0199b0a0-0000-7000-8000-00000000b002';

    public static function board(string $id): PlaceInterface
    {
        return new class($id, self::KITCHEN === $id ? 'Kitchen board' : 'Retired board') implements PlaceInterface {
            public function __construct(private string $id, private string $name)
            {
            }

            public function getPlaceKind(): string
            {
                return NotesPlaces::KIND;
            }

            public function getPlaceId(): string
            {
                return $this->id;
            }

            public function getName(): string
            {
                return $this->name;
            }
        };
    }

    public function kind(): string
    {
        return self::KIND;
    }

    public function label(): string
    {
        return 'Notice boards';
    }

    public function places(): iterable
    {
        yield self::board(self::KITCHEN);
    }

    public function find(string $id): ?PlaceInterface
    {
        return \in_array($id, [self::KITCHEN, self::RETIRED], true) ? self::board($id) : null;
    }
}
