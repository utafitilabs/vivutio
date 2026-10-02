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
 * Six verbs, and no seventh.
 *
 * Every concern in the product is acted on through the same six words. They
 * are fixed here, in the contracts, so that a module cannot add one: a matrix
 * whose columns differ from one module to the next is one nobody can read
 * across, and an organization deciding who may delete would have to learn a
 * new word for it in every module.
 *
 * A concern declares which of the six it supports, and the matrix draws a
 * cell only where it does, so there is never a checkbox that means nothing.
 *
 * Two of the six are kept apart deliberately. Delete is separate from Manage
 * because managing is the ordinary work of a supervisor and deleting cannot be
 * undone; somebody can be trusted with one and not the other. Export is
 * separate from Read for the same kind of reason: reading a record on screen
 * and carrying a list of them out of the system are different acts.
 *
 * Administering the team is not a seventh verb. It is the team's own concerns
 * with Configure and Delete on them.
 */
enum Verb: string
{
    case Read = 'read';
    case Record = 'record';
    case Manage = 'manage';
    case Configure = 'configure';
    case Delete = 'delete';
    case Export = 'export';

    /** The column head in the grants matrix. */
    public function label(): string
    {
        return match ($this) {
            self::Read => 'Read',
            self::Record => 'Record',
            self::Manage => 'Manage',
            self::Configure => 'Configure',
            self::Delete => 'Delete',
            self::Export => 'Export',
        };
    }

    /** The sentence under the column, for whoever is handing the power over. */
    public function meaning(): string
    {
        return match ($this) {
            self::Read => 'Open a page or a record, and see the figures.',
            self::Record => 'Create a record, or add a file to one.',
            self::Manage => 'Act on records other people created: confirm, decline, cancel, approve, amend.',
            self::Configure => 'Set what a module or a section runs on: types, lists, a module being on or off.',
            self::Delete => 'Remove something irreversibly.',
            self::Export => 'Take data out of the system: a spreadsheet, a document, a report.',
        };
    }
}
