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

namespace Vivutio\Core\Tests\Application;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

/**
 * The application the core's own specifications run inside: every core bundle
 * in one kernel, the way an installation holds them. It is export-ignored, so
 * nobody installs it.
 *
 * It sits at the repository root, outside every bundle, because the bundles are
 * released together and a specification here is about them together.
 *
 * The bundles come from config/bundles.php, which the trait reads by itself.
 *
 * @see vendor/symfony/framework-bundle/Kernel/MicroKernelTrait.php — registerBundles()
 */
final class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
        ]);
    }
}
