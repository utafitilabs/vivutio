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

namespace Vivutio\Core\Tests\Core;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Vivutio\Bundle\IdentityBundle\Access\ConcernCatalogue;
use Vivutio\Contracts\Access\Grant;
use Vivutio\Contracts\Access\Verb;
use Vivutio\Core\Tests\Application\Fixtures\NotesScopes;
use Vivutio\Core\Tests\Application\Kernel;

/**
 * A package puts its concerns and its scopes in an installation's catalogue by
 * tagging what declares them, and by nothing else: no list is edited, and the
 * core is not told the package exists.
 */
final class EveryDeclarationReachesTheCatalogueTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testATaggedDeclarationOfConcernsIsInTheCatalogue(): void
    {
        $catalogue = self::catalogue();

        self::assertSame('Notes', $catalogue->declarerOf('notes'));
        self::assertTrue($catalogue->has(Grant::of('notes', Verb::Record)));
        self::assertContains('notes.read', $catalogue->pairs());
    }

    public function testATaggedDeclarationOfAScopeIsInTheCatalogue(): void
    {
        $catalogue = self::catalogue();

        self::assertSame('Notes', $catalogue->scopeDeclarerOf(NotesScopes::NOTEBOOKS));
        self::assertSame('Notebooks', $catalogue->scope(NotesScopes::NOTEBOOKS)?->label);
    }

    private static function catalogue(): ConcernCatalogue
    {
        self::bootKernel();

        $catalogue = self::getContainer()->get(Kernel::CATALOGUE);
        self::assertInstanceOf(ConcernCatalogue::class, $catalogue);

        return $catalogue;
    }
}
