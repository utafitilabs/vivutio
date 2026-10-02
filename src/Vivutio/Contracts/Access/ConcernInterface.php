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
 * A thing the product lets somebody act on.
 *
 * A concern exists only by declaration. The code that owns a thing says this
 * is a concern of mine, these are the verbs it supports, and this one is
 * sensitive; the positions page is generated from those declarations and
 * shows nothing else. There is no list of permissions kept by hand in the
 * middle of the product, so a package's power cannot appear on a page without
 * its owner having said so, and cannot survive the package being removed.
 *
 * Implement it by constructing {@see Concern} unless there is a reason not to:
 * the interface is here so a package may answer with a type of its own, and
 * the value object is here so that almost nobody needs to.
 */
interface ConcernInterface
{
    /**
     * The key a route, a control and a grant all name it by: lowercase
     * letters, digits and hyphens. Unique across the whole installation; the
     * catalogue refuses a second declaration of one key and names both
     * declarers.
     */
    public function key(): string;

    /** The row label in the grants matrix, in the product's words. */
    public function label(): string;

    /**
     * One sentence saying what this concern is about, printed under the row.
     * Required: a matrix half of whose rows explain themselves is one an
     * administrator stops reading.
     */
    public function description(): string;

    /**
     * Which of the six this concern supports. The matrix draws a cell only
     * where the verb is here.
     *
     * @return list<Verb>
     */
    public function verbs(): array;

    /**
     * The keys of the scopes a grant on this concern may be held at: the
     * core's own ({@see Scope::builtIn()}) or one a package declared.
     *
     * @return list<string>
     */
    public function scopes(): array;

    /**
     * Whether this is a fact about a person or about a case that an
     * organization may want withheld without withholding the page it sits on:
     * personal details, money, the bytes of a file. Marked in the matrix.
     */
    public function isSensitive(): bool;

    /**
     * The declaring package's own words for {@see Scope::OWN}, shown as the
     * scope value under that package's group. Null when the concern does not
     * offer it.
     */
    public function ownWords(): ?string;

    /**
     * The slug of the module this concern belongs to, or null for a concern
     * of the core. A department runs a set of modules, so this is how a
     * concern is known to fall inside what a department allows; a concern
     * with no module has no department dimension to ask about.
     */
    public function moduleSlug(): ?string;

    /**
     * Whether only the tiers above the matrix hold this verb. A position can
     * never carry it: the matrix does not draw it, a save naming it is
     * refused, and a position that still stores it is not honoured. It is for
     * the pairs that confer power over people, so a seat cannot raise itself
     * or hand on more than it holds.
     */
    public function isTierOnly(Verb $verb): bool;

    public function supports(Verb $verb): bool;

    public function offers(string $scope): bool;
}
