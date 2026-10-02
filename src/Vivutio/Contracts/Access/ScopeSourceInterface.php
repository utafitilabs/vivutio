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
 * How a package offers a scope of its own.
 *
 * Whoever owns the thing a grant can be limited to declares the scope. The
 * core cannot: it does not know what a package's records belong to, and a
 * scope it invented for one package would be a scope every installation
 * carries whether it has that package or not.
 *
 * Declaring a scope grants nobody anything. A position gains a way to be
 * limited; whether any position uses it is the organization's business.
 *
 * A reusable bundle is not autoconfigured, so the tag goes on by hand.
 */
interface ScopeSourceInterface
{
    /** The tag that puts a declaration in the installation's catalogue of scopes. */
    public const string TAG = 'vivutio.access.scopes';

    /**
     * Who is declaring these, in the product's words. It is how an
     * administrator sees which package a scope came with, and which package
     * removing would take it away.
     */
    public function declaredBy(): string;

    /**
     * @return iterable<Scope>
     */
    public function scopes(): iterable;
}
