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

namespace Vivutio\Contracts\Deletion;

/**
 * How a package answers for its rows when a Super Admin deletes a record (as
 * uhifadhi ruled it, 28 September): the record and what goes with it, counted
 * first, removed in one transaction, the core naming no package.
 *
 * One contributor owns a kind of record: it describes the record and removes
 * it, last. Every other contributor that has rows under it describes nothing,
 * says what of its own goes or stays, and removes or unlinks its rows first,
 * so nothing is left pointing at a row about to go.
 *
 * Nothing here asks who is deleting: the core has checked that it is a Super
 * Admin and that the record's reference was typed.
 *
 * A reusable bundle is not autoconfigured, so the tag goes on by hand.
 */
interface DeletionContributorInterface
{
    public const string TAG = 'vivutio.deletion';

    /** Whether this contributor has anything to say about the record. */
    public function supports(object $record): bool;

    /** The record as its owner describes it; null from every other contributor. */
    public function describe(object $record): ?DeletionSubject;

    /**
     * What of this contributor's goes with the record.
     *
     * @return list<DeletionLine>
     */
    public function whatGoes(object $record): array;

    /**
     * What of this contributor's is linked to the record and stays.
     *
     * @return list<DeletionLine>
     */
    public function whatStays(object $record): array;

    /** Removes or unlinks this contributor's rows; the owner removes the record itself. */
    public function delete(object $record): void;
}
