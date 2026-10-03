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

namespace Vivutio\Bundle\ShellBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * The home of what every screen shares: the design system, the layouts, the
 * navigation and the dashboard modules contribute to.
 */
final class ShellBundle extends AbstractBundle
{
    /**
     * The class sits at the package root, beside the package's composer.json.
     * AbstractBundle assumes a class one directory down, in src/, and answers
     * with the directory above the class's own.
     *
     * @see https://symfony.com/doc/current/bundles.html — the directory
     *      structure "follows a set of conventions, but is flexible to be
     *      adjusted if needed"
     * @see vendor/symfony/dependency-injection/Kernel/AbstractBundle.php — getPath()
     */
    public function getPath(): string
    {
        return __DIR__;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @see https://symfony.com/doc/current/bundles/extension.html — loadExtension() in a bundle extending AbstractBundle
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('config/services.php');
    }
}
