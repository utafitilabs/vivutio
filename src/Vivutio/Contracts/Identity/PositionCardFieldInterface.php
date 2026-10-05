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

namespace Vivutio\Contracts\Identity;

use Vivutio\Contracts\Place\PlaceInterface;

/**
 * A field a package adds to a person's Position card, drawn in the card,
 * checked with it and saved with it, and said in a line beside the person's
 * record. The card's own fields (the position, the posting, the departments)
 * stay the core's.
 *
 * A reusable bundle is not autoconfigured, so the tag goes on by hand.
 */
interface PositionCardFieldInterface
{
    public const string TAG = 'vivutio.identity.position_card';

    /** The template drawn inside the card, given what context() returns. */
    public function template(): string;

    /**
     * What the template is drawn with: what is stored for the person, or what
     * was sent when a save is shown back.
     *
     * @param string                    $person   the person's uuid
     * @param array<string, mixed>|null $sent     the card's form as sent, or null for what is stored
     * @param PlaceInterface|null       $postedAt where they are, or would be, posted
     *
     * @return array<string, mixed>
     */
    public function context(string $person, ?array $sent, ?PlaceInterface $postedAt): array;

    /**
     * What is wrong with what was sent, by the field it is about; nothing is
     * saved, the core's fields included, while anything is.
     *
     * @param array<string, mixed> $sent
     *
     * @return array<string, string>
     */
    public function check(string $person, array $sent, ?PlaceInterface $postedAt): array;

    /**
     * Keeps what was sent, after check() found nothing wrong and the card's
     * own fields were saved.
     *
     * @param array<string, mixed> $sent
     */
    public function save(string $person, array $sent, ?PlaceInterface $postedAt): void;

    /**
     * What the person's record says of it, label first, or null to say nothing.
     *
     * @return array{string, string}|null
     */
    public function summary(string $person): ?array;
}
