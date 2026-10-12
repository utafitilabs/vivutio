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
 * One card a package puts on the dashboard: the latest few of something,
 * how many there are in all, and one door to the package's own page. A card
 * never grows with its data.
 */
final readonly class DashboardCard
{
    public const int MOST_ROWS = 5;

    /**
     * @param string                $kicker          the package's word for what it shows, "Tour bookings"
     * @param list<DashboardRow>    $rows            at most five
     * @param int                   $total           how many there are in all, the rows among them
     * @param string                $empty           what the card says with no rows, "Nothing is overdue"
     * @param string                $door            the door's words, "All tour bookings"
     * @param string                $route           the page the door opens
     * @param string                $attribute       what one must hold to see it, the page's own `<concern>.<verb>`
     * @param array<string, string> $routeParameters
     * @param int                   $order           where it stands among every package's cards, the lowest first
     */
    public function __construct(
        public string $title,
        public string $kicker,
        public string $caption,
        public array $rows,
        public int $total,
        public string $empty,
        public string $door,
        public string $route,
        public string $attribute,
        public array $routeParameters = [],
        public int $order = 0,
    ) {
        if ('' === trim($title)) {
            throw new \InvalidArgumentException(\sprintf('The dashboard card for "%s" has no title.', $route));
        }
        if (\count($rows) > self::MOST_ROWS || $total < \count($rows)) {
            throw new \InvalidArgumentException(\sprintf('The card "%s" shows at most %d rows, out of a total not below them.', $title, self::MOST_ROWS));
        }
    }
}
