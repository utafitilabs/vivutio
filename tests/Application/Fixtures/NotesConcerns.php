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

namespace Vivutio\Core\Tests\Application\Fixtures;

use Vivutio\Contracts\Access\Concern;
use Vivutio\Contracts\Access\ConcernSourceInterface;
use Vivutio\Contracts\Access\Scope;
use Vivutio\Contracts\Access\Verb;

/**
 * What a package declares, played by an invented one. The core holds no
 * module, so a specification about what the core does with a module's
 * declarations stands one up here.
 */
final class NotesConcerns implements ConcernSourceInterface
{
    public function declaredBy(): string
    {
        return 'Notes';
    }

    public function concerns(): iterable
    {
        yield new Concern(
            key: 'notes',
            label: 'Notes',
            description: 'What is written in a notebook.',
            verbs: [Verb::Read, Verb::Record],
            scopes: [Scope::ORGANIZATION, NotesScopes::NOTEBOOKS],
            moduleSlug: 'notes',
        );
    }
}
