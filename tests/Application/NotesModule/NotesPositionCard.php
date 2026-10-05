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

use Vivutio\Contracts\Identity\PositionCardFieldInterface;
use Vivutio\Contracts\Place\PlaceInterface;

/**
 * A package's field on the Position card, played by a desk: drawn in the
 * card, refused when it is "Busy", kept with the card, and said beside the
 * person's record.
 */
final class NotesPositionCard implements PositionCardFieldInterface
{
    /** @var array<string, string> desks by person uuid */
    public static array $desks = [];

    public function template(): string
    {
        return '@Notes/position_card.html.twig';
    }

    public function context(string $person, ?array $sent, ?PlaceInterface $postedAt): array
    {
        $typed = $sent['notes_desk'] ?? null;

        return ['desk' => \is_string($typed) ? $typed : (self::$desks[$person] ?? ''), 'posted_at' => $postedAt?->getName()];
    }

    public function check(string $person, array $sent, ?PlaceInterface $postedAt): array
    {
        return 'Busy' === ($sent['notes_desk'] ?? null) ? ['notes_desk' => 'That desk is taken.'] : [];
    }

    public function save(string $person, array $sent, ?PlaceInterface $postedAt): void
    {
        $desk = $sent['notes_desk'] ?? '';
        self::$desks[$person] = \is_string($desk) ? $desk : '';
    }

    public function summary(string $person): ?array
    {
        return isset(self::$desks[$person]) ? ['Desk', self::$desks[$person]] : null;
    }
}
