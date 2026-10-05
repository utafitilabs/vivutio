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

namespace Vivutio\Bundle\ShellBundle\Tests\Unit\Twig;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Vivutio\Bundle\ShellBundle\Twig\MenuExtension;
use Vivutio\Contracts\Shell\MenuEntry;
use Vivutio\Contracts\Shell\MenuSourceInterface;

/**
 * A page is in the menu only where the installation mounts it: a package
 * whose routes are not imported yet leaves its entry out rather than breaking
 * every page the menu is drawn on.
 */
final class MenuExtensionTest extends TestCase
{
    public function testAnEntryIsDrawnOnlyWhereItsPageIsMounted(): void
    {
        $menu = $this->menu(['notes' => '/notes', 'note' => '/notes/{id}']);

        self::assertSame(['notes'], array_map(static fn (MenuEntry $entry): string => $entry->route, $menu->entries()));
        self::assertTrue($menu->mounted('notes'));
        self::assertTrue($menu->mounted('note'), 'a page that takes parameters is mounted');
        self::assertFalse($menu->mounted('ledger'));
    }

    /**
     * @param array<string, string> $routes
     */
    private function menu(array $routes): MenuExtension
    {
        $collection = new RouteCollection();
        foreach ($routes as $name => $path) {
            $collection->add($name, new Route($path));
        }
        $source = new class implements MenuSourceInterface {
            public function entries(): iterable
            {
                yield new MenuEntry('notes', 'Notes', 'notes.read', 'notes', 'notes');
                yield new MenuEntry('ledger', 'Ledger', 'ledger.read', 'ledger', 'ledger');
            }
        };
        $authorization = new class implements AuthorizationCheckerInterface {
            public function isGranted(mixed $attribute, mixed $subject = null, mixed $accessDecision = null): bool
            {
                return true;
            }
        };

        return new MenuExtension([$source], $authorization, new UrlGenerator($collection, new RequestContext()));
    }
}
