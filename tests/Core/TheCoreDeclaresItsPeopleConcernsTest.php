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
use Vivutio\Core\Tests\Application\Kernel;

/**
 * What the core lets a position grant about people. Only what some route or
 * control checks is declared; the route walk refuses a box nothing checks.
 */
final class TheCoreDeclaresItsPeopleConcernsTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        while (true) {
            $previous = set_exception_handler(static fn () => null);
            restore_exception_handler();
            if (null === $previous) {
                break;
            }
            restore_exception_handler();
        }
    }

    public function testTheTeamsConcernsAreDeclaredByTheCore(): void
    {
        $catalogue = $this->catalogue();

        self::assertTrue($catalogue->has(Grant::of('directory', Verb::Read)), 'who is on the team');
        self::assertFalse($catalogue->has(Grant::of('directory', Verb::Export)), 'the team is read on screen and never exported');
        self::assertTrue($catalogue->has(Grant::of('personal_details', Verb::Read)), 'a person\'s contact details');

        self::assertSame('Team', $catalogue->declarerOf('directory'));
        self::assertNull($catalogue->moduleOf('directory'), 'the core\'s, so no department question');
        self::assertNull($catalogue->moduleOf('personal_details'));
    }

    /** Knowing somebody is on the team and knowing how to reach them are separate grants. */
    public function testPersonalDetailsAreSensitiveAndTheDirectoryIsNot(): void
    {
        self::assertTrue($this->catalogue()->isSensitive('personal_details'));
        self::assertFalse($this->catalogue()->isSensitive('directory'));
    }

    public function testEveryPairOfThemIsOneAPositionMayCarry(): void
    {
        foreach (['directory.read', 'personal_details.read'] as $pair) {
            self::assertContains($pair, $this->catalogue()->positionPairs());
        }
    }

    private function catalogue(): ConcernCatalogue
    {
        $catalogue = static::getContainer()->get(Kernel::CATALOGUE);
        self::assertInstanceOf(ConcernCatalogue::class, $catalogue);

        return $catalogue;
    }
}
