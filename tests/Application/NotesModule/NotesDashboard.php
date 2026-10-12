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

use Vivutio\Contracts\Shell\DashboardCard;
use Vivutio\Contracts\Shell\DashboardFigure;
use Vivutio\Contracts\Shell\DashboardRow;
use Vivutio\Contracts\Shell\DashboardSourceInterface;

final class NotesDashboard implements DashboardSourceInterface
{
    public function figures(): iterable
    {
        yield new DashboardFigure('Written this week', '3', 'in 2 notebooks', 'notes.read', 10);
        yield new DashboardFigure('Notebooks', '2', '', 'notes.record', 20);
    }

    public function cards(): iterable
    {
        yield new DashboardCard('Unread', 'Notes', 'the oldest first', [], 0, 'Nothing is unread', 'All notes', NotesController::LIST, 'notes.read', order: 30);
        yield new DashboardCard('Latest notes', 'Notes', 'the newest first', [
            new DashboardRow('NT-0005', 'Field notebook', ['Mon 12 Oct'], 'New', 'warn'),
            new DashboardRow('NT-0004', 'Field notebook', ['Sun 11 Oct']),
        ], 5, 'Nothing is written yet', 'All notes', NotesController::LIST, 'notes.read', order: 10);
        yield new DashboardCard('Pinned', 'Notes', 'kept at hand', [new DashboardRow('NT-0001', 'Office notebook')], 1, 'Nothing is pinned', 'All notes', NotesController::LIST, 'notes.read', order: 20);
    }
}
