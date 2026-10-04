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
 * How a package puts its pages in the menu, without the core naming it.
 *
 *     $services->set(PropertyMenu::class)->tag(MenuSourceInterface::TAG);
 */
interface MenuSourceInterface
{
    public const string TAG = 'vivutio.shell.menu';

    /**
     * @return iterable<MenuEntry> in the order they are drawn
     */
    public function entries(): iterable;
}
