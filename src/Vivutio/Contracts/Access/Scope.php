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
 * How far a grant reaches.
 *
 * A position says which scopes it allows, and where a person is placed says
 * which one they actually hold. That split is the model's one lever over
 * reach: a position meant to be local cannot be widened by mistake when
 * somebody is seated in it, because the wider scope is not on offer.
 *
 * The core has three of its own. Any other belongs to the package that owns
 * the thing a grant is limited to, and that package declares it through
 * {@see ScopeSourceInterface}; the core learns of it only then, and never
 * names it.
 *
 * One thing a scope is never decided by is who created the record. A page is
 * scoped by what the record belongs to, never by whose name is on it.
 */
final readonly class Scope
{
    public const string ORGANIZATION = 'organization';
    public const string DEPARTMENT = 'department';
    public const string OWN = 'own';

    /**
     * @param string $key   what a concern names it by: lowercase letters, digits and hyphens
     * @param string $label the word for it in the grants matrix
     * @param string $reach one sentence saying how far a grant at this scope reaches
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $reach,
    ) {
        if (1 !== preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $key)) {
            throw new \InvalidArgumentException(\sprintf('The scope key "%s" is not a slug. Use lowercase letters, digits and hyphens: it is the word a concern names this scope by.', $key));
        }

        if ('' === trim($label)) {
            throw new \InvalidArgumentException(\sprintf('The scope "%s" was declared without a label. It is the word an administrator reads when choosing how far a position reaches.', $key));
        }

        if ('' === trim($reach)) {
            throw new \InvalidArgumentException(\sprintf('The scope "%s" does not say how far it reaches. Say it in one sentence: it is printed beside the choice.', $key));
        }
    }

    public static function organization(): self
    {
        return new self(self::ORGANIZATION, 'Organization', 'Everything.');
    }

    public static function department(): self
    {
        return new self(self::DEPARTMENT, 'Department', 'What belongs to the department the person is in, and to the departments they support.');
    }

    public static function own(): self
    {
        return new self(self::OWN, 'Own', 'What the package that owns the concern calls mine, in its own words.');
    }

    /**
     * The scopes the core has without any package declaring them.
     *
     * @return list<self>
     */
    public static function builtIn(): array
    {
        return [self::organization(), self::department(), self::own()];
    }
}
