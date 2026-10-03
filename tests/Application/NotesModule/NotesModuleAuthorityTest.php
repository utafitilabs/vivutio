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

use Vivutio\Bundle\IdentityBundle\Test\AuthorityTestCase;
use Vivutio\Bundle\IdentityBundle\Test\Probe;
use Vivutio\Core\Tests\Application\Kernel;

/**
 * A stand-in module held to the five proofs exactly as a real module's suite
 * would be: it names its probes, its directory and its table, and nothing
 * else. Its routes are told from the core's by where their controller is.
 */
final class NotesModuleAuthorityTest extends AuthorityTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    protected static function probes(): array
    {
        return [
            new Probe(NotesController::LIST, 'GET', '/notes'),
            new Probe(NotesController::WRITE, 'POST', '/notes', ['text' => 'A note']),
        ];
    }

    protected static function packageDirectory(): string
    {
        return __DIR__;
    }

    protected static function authorityTable(): string
    {
        return __DIR__.'/authority-table.md';
    }
}
