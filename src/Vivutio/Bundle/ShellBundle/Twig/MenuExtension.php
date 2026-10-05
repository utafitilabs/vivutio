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

namespace Vivutio\Bundle\ShellBundle\Twig;

use Symfony\Component\Routing\Exception\MissingMandatoryParametersException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Vivutio\Contracts\Shell\MenuEntry;
use Vivutio\Contracts\Shell\MenuSourceInterface;

/**
 * `menu_entries()`: the pages the installed packages put in the menu, each
 * only for whoever holds the pair it names, and only where the installation
 * mounts it. `mounted()` asks the same of a page the frame names itself.
 *
 * A bundle's routes are imported by the installation, never by the bundle, so
 * a package can be installed before its pages are mounted; its entry then
 * waits rather than breaking every page the menu is drawn on.
 *
 * @see https://twig.symfony.com/doc/3.x/advanced.html#functions
 * @see https://symfony.com/doc/current/bundles/best_practices.html#routing
 */
final class MenuExtension extends AbstractExtension
{
    /**
     * @param iterable<MenuSourceInterface> $sources
     */
    public function __construct(
        private readonly iterable $sources,
        private readonly AuthorizationCheckerInterface $authorization,
        private readonly UrlGeneratorInterface $urls,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('menu_entries', $this->entries(...)),
            new TwigFunction('mounted', $this->mounted(...)),
        ];
    }

    /**
     * @return list<MenuEntry>
     */
    public function entries(): array
    {
        $entries = [];
        foreach ($this->sources as $source) {
            foreach ($source->entries() as $entry) {
                if ($this->mounted($entry->route) && $this->authorization->isGranted($entry->attribute)) {
                    $entries[] = $entry;
                }
            }
        }

        return $entries;
    }

    /**
     * Whether the installation mounts the named page. The compiled generator
     * knows every route, so asking it costs no loading of the routes.
     */
    public function mounted(string $route): bool
    {
        try {
            $this->urls->generate($route);
        } catch (RouteNotFoundException) {
            return false;
        } catch (MissingMandatoryParametersException) {
            return true;
        }

        return true;
    }
}
