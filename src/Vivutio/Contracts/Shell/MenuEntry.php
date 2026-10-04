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

namespace Vivutio\Contracts\Shell;

/**
 * One page a package puts in the menu.
 *
 * The menu draws it only for whoever holds the pair it names, the same pair
 * the page's route checks, so the menu never offers a door that refuses.
 */
final readonly class MenuEntry
{
    /**
     * @param string $route     the route of the page it opens
     * @param string $label     the word in the menu
     * @param string $attribute what one must hold to see it, the page's own `<concern>.<verb>`
     * @param string $icon      the inner markup of a 24×24 stroked icon, lucide's paths
     * @param string $here      the key a page of the package names itself by, to be marked as the one open
     */
    public function __construct(
        public string $route,
        public string $label,
        public string $attribute,
        public string $icon,
        public string $here,
    ) {
        if ('' === trim($label)) {
            throw new \InvalidArgumentException(\sprintf('The menu entry for "%s" has no label.', $route));
        }
    }
}
