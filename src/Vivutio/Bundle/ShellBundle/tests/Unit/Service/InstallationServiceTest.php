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

namespace Vivutio\Bundle\ShellBundle\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Vivutio\Bundle\ShellBundle\Model\InstalledPackage;
use Vivutio\Bundle\ShellBundle\Service\InstallationService;

/**
 * What an installation is made of: the core first, then every vivutio
 * package it installed. The installation itself, the project made from the
 * skeleton, is not listed, nor a name the core answers to without a
 * directory of its own.
 */
final class InstallationServiceTest extends TestCase
{
    public function testTheInstallationListsWhatItInstalledAndNotItself(): void
    {
        $packages = InstallationService::packagesIn([[
            'root' => ['name' => 'vivutio/skeleton', 'type' => 'project'],
            'versions' => [
                'vivutio/skeleton' => ['pretty_version' => '0.1.4', 'install_path' => __DIR__],
                'vivutio/touring-module' => ['pretty_version' => 'v0.1.0', 'install_path' => __DIR__],
                'vivutio/vivutio' => ['pretty_version' => 'v0.1.21', 'install_path' => __DIR__],
                'vivutio/identity-bundle' => ['pretty_version' => 'v0.1.21'],
                'symfony/console' => ['pretty_version' => 'v8.1.8', 'install_path' => __DIR__],
            ],
        ]]);

        self::assertSame(['vivutio/vivutio 0.1.21', 'vivutio/touring-module 0.1.0'], array_map(static fn (InstalledPackage $package): string => $package->name.' '.$package->version, $packages));
    }

    public function testTheCoreAsItsOwnRootIsListed(): void
    {
        $packages = InstallationService::packagesIn([[
            'root' => ['name' => 'vivutio/vivutio', 'type' => 'library'],
            'versions' => ['vivutio/vivutio' => ['pretty_version' => '0.1.x-dev', 'install_path' => __DIR__]],
        ]]);

        self::assertSame(['vivutio/vivutio'], array_map(static fn (InstalledPackage $package): string => $package->name, $packages));
    }
}
