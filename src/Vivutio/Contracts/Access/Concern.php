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
 * The ordinary way to declare a concern: construct one.
 *
 * Everything the matrix needs about a row, checked when it is written and not
 * when it is drawn. A declaration that cannot be shown honestly, with no
 * sentence, no verb, or an "own" scope the package has no word for, does not
 * construct.
 *
 * Whether a scope it names was declared by anybody is not checked here: that
 * takes every package's declarations together, and is the catalogue's to
 * refuse.
 */
final readonly class Concern implements ConcernInterface
{
    private const string SLUG = '/^[a-z0-9]+(_[a-z0-9]+)*$/';

    /** @var list<Verb> */
    private array $verbs;

    /** @var list<string> */
    private array $scopes;

    /** @var list<Verb> */
    private array $tierOnly;

    /**
     * @param list<Verb>   $verbs      which of the six this concern supports
     * @param list<string> $scopes     the keys of the scopes a grant on it may be held at
     * @param string|null  $ownWords   the package's own words for "mine", required
     *                                 when and only when the own scope is offered
     * @param string|null  $moduleSlug the module that owns it, null for the core's own
     * @param list<Verb>   $tierOnly   the verbs only the tiers above the matrix hold
     */
    public function __construct(
        private string $key,
        private string $label,
        private string $description,
        array $verbs,
        array $scopes,
        private bool $sensitive = false,
        private ?string $ownWords = null,
        private ?string $moduleSlug = null,
        array $tierOnly = [],
    ) {
        if (1 !== preg_match(self::SLUG, $key)) {
            throw new \InvalidArgumentException(\sprintf('The concern key "%s" is not a slug. Use lowercase letters, digits and underscores: it is the word a route, a control and a grant all name this concern by.', $key));
        }

        if ('' === trim($description)) {
            throw new \InvalidArgumentException(\sprintf('The concern "%s" was declared without a description. Say in one sentence what it is about: it is printed under the row in the grants matrix.', $key));
        }

        if ([] === $verbs) {
            throw new \InvalidArgumentException(\sprintf('The concern "%s" supports no verb. Declare at least one verb, or do not declare the concern: a row with no cell is a row nobody can grant.', $key));
        }

        if ([] === $scopes) {
            throw new \InvalidArgumentException(\sprintf('The concern "%s" offers no scope. Declare at least one: a grant that reaches nowhere is a grant that does nothing.', $key));
        }

        $seen = [];
        foreach ($verbs as $verb) {
            if (isset($seen[$verb->value])) {
                throw new \InvalidArgumentException(\sprintf('The concern "%s" names the verb "%s" twice. Each verb is declared once.', $key, $verb->value));
            }
            $seen[$verb->value] = true;
        }

        $seen = [];
        foreach ($scopes as $scope) {
            if (1 !== preg_match(self::SLUG, $scope)) {
                throw new \InvalidArgumentException(\sprintf('The concern "%s" offers "%s", which is not a scope key. A scope is named by its key: lowercase letters, digits and underscores.', $key, $scope));
            }

            if (isset($seen[$scope])) {
                throw new \InvalidArgumentException(\sprintf('The concern "%s" names the scope "%s" twice. Each scope is declared once.', $key, $scope));
            }
            $seen[$scope] = true;
        }

        $offersOwn = \in_array(Scope::OWN, $scopes, true);

        if ($offersOwn && (null === $ownWords || '' === trim($ownWords))) {
            throw new \InvalidArgumentException(\sprintf('The concern "%s" offers the "own" scope without saying what own means. Give the package\'s own words for it, because the core has none.', $key));
        }

        if (!$offersOwn && null !== $ownWords) {
            throw new \InvalidArgumentException(\sprintf('The concern "%s" gives words for "own" but does not offer the "own" scope, so the words would name nothing.', $key));
        }

        foreach ($tierOnly as $verb) {
            if (!\in_array($verb, $verbs, true)) {
                throw new \InvalidArgumentException(\sprintf('The concern "%s" holds "%s" for the tiers alone but does not support it. Name only verbs the concern declares.', $key, $verb->value));
            }
        }

        $this->verbs = $verbs;
        $this->scopes = $scopes;
        $this->tierOnly = array_values(array_unique($tierOnly, \SORT_REGULAR));
    }

    public function key(): string
    {
        return $this->key;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function verbs(): array
    {
        return $this->verbs;
    }

    public function scopes(): array
    {
        return $this->scopes;
    }

    public function isSensitive(): bool
    {
        return $this->sensitive;
    }

    public function ownWords(): ?string
    {
        return $this->ownWords;
    }

    public function moduleSlug(): ?string
    {
        return $this->moduleSlug;
    }

    public function isTierOnly(Verb $verb): bool
    {
        return \in_array($verb, $this->tierOnly, true);
    }

    public function supports(Verb $verb): bool
    {
        return \in_array($verb, $this->verbs, true);
    }

    public function offers(string $scope): bool
    {
        return \in_array($scope, $this->scopes, true);
    }
}
