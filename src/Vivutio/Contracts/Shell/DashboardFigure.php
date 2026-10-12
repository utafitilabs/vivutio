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
 * One figure in the dashboard's band: "Held, to confirm · 4 · 1 lets go tomorrow".
 */
final readonly class DashboardFigure
{
    /**
     * @param string $attribute what one must hold to see it, the `<concern>.<verb>` of the page it is read from
     * @param int    $order     where it stands among every package's figures, the lowest first
     */
    public function __construct(
        public string $label,
        public string $value,
        public string $qualifier,
        public string $attribute,
        public int $order = 0,
    ) {
        if ('' === trim($label) || '' === trim($value)) {
            throw new \InvalidArgumentException('A dashboard figure has a label and a value.');
        }
    }
}
