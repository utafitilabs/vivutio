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

namespace Vivutio\Bundle\IdentityBundle\Access;

use Vivutio\Contracts\Access\ConcernInterface;
use Vivutio\Contracts\Access\ConcernSourceInterface;
use Vivutio\Contracts\Access\Grant;
use Vivutio\Contracts\Access\Scope;
use Vivutio\Contracts\Access\ScopeSourceInterface;
use Vivutio\Contracts\Access\Verb;

/**
 * Everything there is to hold a permission about in this installation, folded
 * together from whoever declared it.
 *
 * It keeps no list of its own: it walks the declared sources and reports what
 * they said. There is no privileged catalogue in the middle of the product,
 * which is what stops a package's power appearing on a page its owner never
 * declared, or surviving the package being removed.
 *
 * The sources are read on every call and never folded in the constructor.
 * What is installed is a fact about the running container, so a package taken
 * out of an installation stops being answerable at once.
 *
 * A collision is refused, never merged. Two packages declaring one key is an
 * installation that cannot say what the key means, and keeping the first
 * would make the winner depend on registration order, a difference nobody can
 * see. The same holds for a concern that names a scope nobody declared: the
 * catalogue could not say how far a grant on it reaches. Both throw, naming
 * the declarers, on the first call.
 */
final readonly class ConcernCatalogue
{
    /** Who declares the scopes no package has to: {@see Scope::builtIn()}. */
    private const string THE_CORE = 'the core';

    /**
     * @param iterable<ConcernSourceInterface> $concernSources every declaration of concerns, in registration order
     * @param iterable<ScopeSourceInterface>   $scopeSources   every declaration of scopes, in registration order
     */
    public function __construct(
        private iterable $concernSources = [],
        private iterable $scopeSources = [],
    ) {
    }

    /**
     * Every concern, grouped under whoever declared it: the shape the
     * positions page draws, one group per package.
     *
     * @return array<string, list<ConcernInterface>> declarer to its concerns, in declaration order
     */
    public function grouped(): array
    {
        $declaredScopes = $this->scopeDeclarers();

        $grouped = [];
        $seen = [];

        foreach ($this->concernSources as $source) {
            $declarer = $source->declaredBy();
            $grouped[$declarer] ??= [];

            foreach ($source->concerns() as $concern) {
                $key = $concern->key();

                if (isset($seen[$key])) {
                    throw new \LogicException(\sprintf('Two packages declare the concern "%s": %s and %s. A concern key is the word a route, a control and a grant all name it by, so an installation cannot hold two meanings for one. Rename one of them.', $key, $seen[$key], $declarer));
                }

                foreach ($concern->scopes() as $scope) {
                    if (!isset($declaredScopes[$scope])) {
                        throw new \LogicException(\sprintf('The concern "%s", declared by %s, may be held at the scope "%s", which nobody declares. Declare the scope where the thing it limits a grant to is owned, or name one of: %s.', $key, $declarer, $scope, implode(', ', array_keys($declaredScopes))));
                    }
                }

                $seen[$key] = $declarer;
                $grouped[$declarer][] = $concern;
            }
        }

        return $grouped;
    }

    /**
     * @return list<ConcernInterface> in declaration order, declarer by declarer
     */
    public function all(): array
    {
        $concerns = [];
        foreach ($this->grouped() as $group) {
            $concerns = [...$concerns, ...$group];
        }

        return $concerns;
    }

    public function concern(string $key): ?ConcernInterface
    {
        foreach ($this->all() as $concern) {
            if ($key === $concern->key()) {
                return $concern;
            }
        }

        return null;
    }

    /** Which package declared this concern, for the caption on its group. */
    public function declarerOf(string $key): ?string
    {
        foreach ($this->grouped() as $declarer => $concerns) {
            foreach ($concerns as $concern) {
                if ($key === $concern->key()) {
                    return $declarer;
                }
            }
        }

        return null;
    }

    /**
     * Whether this pair is one anybody declared: the concern exists and it
     * supports that verb. A pair the matrix would not draw is not a pair, so
     * a cell that means nothing cannot be granted through a route that names
     * it anyway.
     */
    public function has(Grant $grant): bool
    {
        return $this->concern($grant->concern)?->supports($grant->verb) ?? false;
    }

    /**
     * Every pair this installation offers, as the strings a position stores
     * and a route names. The order is the matrix's: concern by concern, and
     * within a concern the six verbs in their fixed order, so two readings of
     * the catalogue never disagree about it.
     *
     * @return list<string>
     */
    public function pairs(): array
    {
        $pairs = [];
        foreach ($this->all() as $concern) {
            foreach (Verb::cases() as $verb) {
                if ($concern->supports($verb)) {
                    $pairs[] = (string) Grant::of($concern->key(), $verb);
                }
            }
        }

        return $pairs;
    }

    /**
     * Every pair a position may carry, in the matrix's order: every pair
     * less the ones only the tiers hold.
     *
     * @return list<string>
     */
    public function positionPairs(): array
    {
        return array_values(array_filter(
            $this->pairs(),
            fn (string $pair): bool => !$this->isTierOnly($pair),
        ));
    }

    /**
     * Whether only the tiers above the matrix hold this pair. False for a
     * pair nothing declares, and for a string that is not a pair.
     */
    public function isTierOnly(string $pair): bool
    {
        $grant = Grant::tryParse($pair);
        if (null === $grant) {
            return false;
        }

        return $this->concern($grant->concern)?->isTierOnly($grant->verb) ?? false;
    }

    /** The module a concern belongs to, or null for one of the core's own. */
    public function moduleOf(string $key): ?string
    {
        return $this->concern($key)?->moduleSlug();
    }

    /** Whether the concern is a fact about a person or a case. False for one nothing declares. */
    public function isSensitive(string $key): bool
    {
        return $this->concern($key)?->isSensitive() ?? false;
    }

    /**
     * Every scope a grant can be held at here: the core's own first, then
     * each package's in declaration order.
     *
     * @return list<Scope>
     */
    public function scopes(): array
    {
        return array_values(array_map(
            static fn (array $declared): Scope => $declared['scope'],
            $this->scopeDeclarers(),
        ));
    }

    public function scope(string $key): ?Scope
    {
        return $this->scopeDeclarers()[$key]['scope'] ?? null;
    }

    /** Which package declared this scope; null for one of the core's own and for one nobody declared. */
    public function scopeDeclarerOf(string $key): ?string
    {
        $declarer = $this->scopeDeclarers()[$key]['declarer'] ?? null;

        return self::THE_CORE === $declarer ? null : $declarer;
    }

    /**
     * @return array<string, array{scope: Scope, declarer: string}> scope key to the scope and who declared it
     */
    private function scopeDeclarers(): array
    {
        $declared = [];
        foreach (Scope::builtIn() as $scope) {
            $declared[$scope->key] = ['scope' => $scope, 'declarer' => self::THE_CORE];
        }

        foreach ($this->scopeSources as $source) {
            $declarer = $source->declaredBy();

            foreach ($source->scopes() as $scope) {
                if (isset($declared[$scope->key])) {
                    throw new \LogicException(\sprintf('Two declarations of the scope "%s": %s and %s. A scope key is the word every concern names it by, so an installation cannot hold two meanings for one. Rename the later one.', $scope->key, $declared[$scope->key]['declarer'], $declarer));
                }

                $declared[$scope->key] = ['scope' => $scope, 'declarer' => $declarer];
            }
        }

        return $declared;
    }
}
