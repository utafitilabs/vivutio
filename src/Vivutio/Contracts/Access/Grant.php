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
 * One cell of the matrix: a concern and a verb, together.
 *
 * A position grants pairs, a route names the pair it enforces, a control names
 * the pair it is drawn on, and a test holds the three together. That only
 * works while all of them spell a pair the same way, so the spelling lives
 * here and nowhere else: `<concern>.<verb>`, "positions.configure".
 *
 * The verb is the last segment, which is why a concern key may carry
 * underscores and never a dot: "personal_details.read" has exactly one
 * reading, and a concern key with a dot in it would have two.
 *
 * It is a value, not a permission. Holding one of these says nothing about
 * anybody; it is the name of a question. Who may answer it yes is the
 * position's business.
 */
final readonly class Grant implements \Stringable
{
    private function __construct(
        public string $concern,
        public Verb $verb,
    ) {
        if (1 !== preg_match('/^[a-z0-9]+(_[a-z0-9]+)*$/', $concern)) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not a concern key. Use lowercase letters, digits and underscores: a dot would make the pair ambiguous, because the verb is the segment after the last one.', $concern));
        }
    }

    public static function of(string $concern, Verb $verb): self
    {
        return new self($concern, $verb);
    }

    /**
     * Reads a pair somebody wrote down: a route attribute, a stored grant, a
     * control. It refuses and never guesses: an unreadable pair is a grant
     * nobody can reason about, and the model fails closed.
     *
     * @throws \InvalidArgumentException when the string is not a pair
     */
    public static function parse(string $pair): self
    {
        $at = strrpos($pair, '.');
        if (false === $at) {
            throw new \InvalidArgumentException(\sprintf('"%s" names no verb. A grant is written "<concern>.<verb>", as in "positions.configure".', $pair));
        }

        $verb = Verb::tryFrom(substr($pair, $at + 1));
        if (null === $verb) {
            throw new \InvalidArgumentException(\sprintf('"%s" ends in no verb this product has. The six are: %s.', $pair, implode(', ', array_map(static fn (Verb $v): string => $v->value, Verb::cases()))));
        }

        return new self(substr($pair, 0, $at), $verb);
    }

    /** The same, for a string that may legitimately be nonsense: a stored grant left by an uninstalled module. */
    public static function tryParse(string $pair): ?self
    {
        try {
            return self::parse($pair);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    public function equals(self $other): bool
    {
        return $this->concern === $other->concern && $this->verb === $other->verb;
    }

    public function __toString(): string
    {
        return $this->concern.'.'.$this->verb->value;
    }
}
