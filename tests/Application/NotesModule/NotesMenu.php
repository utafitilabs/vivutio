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

use Vivutio\Contracts\Shell\MenuEntry;
use Vivutio\Contracts\Shell\MenuSourceInterface;

final class NotesMenu implements MenuSourceInterface
{
    public function entries(): iterable
    {
        yield new MenuEntry(NotesController::LIST, 'Notes', 'notes.read', '<path d="M4 4h16v16H4z"/>', 'notes');
    }
}
