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

use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Vivutio\Contracts\Shell\MenuEntry;
use Vivutio\Contracts\Shell\MenuSourceInterface;

/**
 * `menu_entries()`: the pages the installed packages put in the menu, each
 * only for whoever holds the pair it names.
 *
 * @see https://twig.symfony.com/doc/3.x/advanced.html#functions
 */
final class MenuExtension extends AbstractExtension
{
    /**
     * @param iterable<MenuSourceInterface> $sources
     */
    public function __construct(
        private readonly iterable $sources,
        private readonly AuthorizationCheckerInterface $authorization,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('menu_entries', $this->entries(...)),
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
                if ($this->authorization->isGranted($entry->attribute)) {
                    $entries[] = $entry;
                }
            }
        }

        return $entries;
    }
}
