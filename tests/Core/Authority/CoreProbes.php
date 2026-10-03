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

namespace Vivutio\Core\Tests\Core\Authority;

use Vivutio\Bundle\IdentityBundle\Controller\SecurityController;
use Vivutio\Bundle\IdentityBundle\Controller\SettingsController;
use Vivutio\Bundle\IdentityBundle\Controller\TeamController;
use Vivutio\Bundle\IdentityBundle\Test\Probe;
use Vivutio\Bundle\ShellBundle\Controller\DashboardController;

/**
 * A probe for every route of the core, and of this application.
 */
final class CoreProbes
{
    /**
     * @return list<Probe>
     */
    public static function all(): array
    {
        return [
            new Probe(SecurityController::SIGN_IN, 'GET', '/login'),
            new Probe(SecurityController::SIGN_OUT, 'GET', '/logout'),
            new Probe(DashboardController::HOME, 'GET', '/'),
            new Probe(DashboardController::MINE, 'GET', '/me'),
            new Probe(TeamController::TEAM, 'GET', '/team'),
            new Probe(SettingsController::ORGANIZATION, 'GET', '/settings/organization'),
            new Probe(SettingsController::CONFIGURE_ORGANIZATION, 'GET', '/settings/configure/organization'),
            new Probe(SettingsController::CONFIGURE_ORGANIZATION, 'POST', '/settings/configure/organization', ['name' => 'Taken over'], formAt: '/settings/configure/organization'),
        ];
    }
}
