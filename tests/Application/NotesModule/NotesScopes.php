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

use Vivutio\Contracts\Access\Scope;
use Vivutio\Contracts\Access\ScopeSourceInterface;

/**
 * A scope a package offers of its own, played by an invented one.
 */
final class NotesScopes implements ScopeSourceInterface
{
    public const string NOTEBOOKS = 'notebooks';

    public function declaredBy(): string
    {
        return 'Notes';
    }

    public function scopes(): iterable
    {
        yield new Scope(self::NOTEBOOKS, 'Notebooks', 'One or more named notebooks.');
    }
}
