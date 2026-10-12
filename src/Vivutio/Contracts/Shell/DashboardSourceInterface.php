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
 * How a package puts its figures and cards on the organization's dashboard,
 * without the core naming it.
 *
 *     $services->set(TourDashboard::class)->args([...])->tag(DashboardSourceInterface::TAG);
 *
 * The dashboard draws a figure or a card only for whoever holds the pair it
 * names, and deals the cards of every package into two columns in turn, by
 * their order.
 */
interface DashboardSourceInterface
{
    public const string TAG = 'vivutio.shell.dashboard';

    /**
     * @return iterable<DashboardFigure>
     */
    public function figures(): iterable;

    /**
     * @return iterable<DashboardCard>
     */
    public function cards(): iterable;
}
