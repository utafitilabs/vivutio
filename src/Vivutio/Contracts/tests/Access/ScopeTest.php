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

namespace Vivutio\Contracts\Tests\Access;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Vivutio\Contracts\Access\Scope;
use Vivutio\Contracts\Access\ScopeSourceInterface;

/**
 * How far a grant reaches. The core knows three reaches of its own; any other
 * is declared by the package that owns the thing a grant is limited to.
 */
final class ScopeTest extends TestCase
{
    public function testTheCoreHasThreeScopesOfItsOwn(): void
    {
        self::assertSame(
            ['organization', 'department', 'own'],
            array_map(static fn (Scope $scope): string => $scope->key, Scope::builtIn()),
        );

        self::assertSame(Scope::ORGANIZATION, Scope::organization()->key);
        self::assertSame(Scope::DEPARTMENT, Scope::department()->key);
        self::assertSame(Scope::OWN, Scope::own()->key);
    }

    #[DataProvider('everyBuiltInScope')]
    public function testEveryBuiltInScopeSaysHowFarItReaches(Scope $scope): void
    {
        self::assertNotSame('', trim($scope->label));
        self::assertNotSame('', trim($scope->reach));
        self::assertStringEndsWith('.', $scope->reach);
    }

    /**
     * @return iterable<string, array{Scope}>
     */
    public static function everyBuiltInScope(): iterable
    {
        foreach (Scope::builtIn() as $scope) {
            yield $scope->key => [$scope];
        }
    }

    public function testAPackageDeclaresAScopeOfItsOwn(): void
    {
        $scope = new Scope('notebooks', 'Notebooks', 'One or more named notebooks.');

        self::assertSame('notebooks', $scope->key);
        self::assertSame('Notebooks', $scope->label);
        self::assertSame('One or more named notebooks.', $scope->reach);
    }

    #[DataProvider('keysThatAreNotSlugs')]
    public function testAScopeKeyIsALowercaseSlug(string $key): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not a slug/');

        new Scope($key, 'Notebooks', 'One or more named notebooks.');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function keysThatAreNotSlugs(): iterable
    {
        yield 'empty' => [''];
        yield 'uppercase' => ['Notebooks'];
        yield 'hyphen' => ['note-books'];
        yield 'leading underscore' => ['_notebooks'];
        yield 'dot' => ['note.books'];
        yield 'space' => ['note books'];
    }

    public function testAScopeWithoutALabelIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/without a label/');

        new Scope('notebooks', '  ', 'One or more named notebooks.');
    }

    public function testAScopeThatDoesNotSayHowFarItReachesIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/how far it reaches/');

        new Scope('notebooks', 'Notebooks', '');
    }

    public function testTheTagIsTheOneTheCoreCollects(): void
    {
        self::assertSame('vivutio.access.scopes', ScopeSourceInterface::TAG);
    }
}
