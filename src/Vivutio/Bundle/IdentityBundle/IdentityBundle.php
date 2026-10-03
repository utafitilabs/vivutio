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

namespace Vivutio\Bundle\IdentityBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Vivutio\Bundle\IdentityBundle\DependencyInjection\TestServicesPass;

/**
 * The home of the organization an installation serves and of the people in
 * it: users, tiers and positions, the permissions every package declares, and
 * the voters that decide each of them.
 */
final class IdentityBundle extends AbstractBundle
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
     * The pass that, in a test container only, lets the authority test base
     * reach every voter and every declaration.
     *
     * @see https://symfony.com/doc/current/service_container/compiler_passes.html — "Working with Compiler Passes in Bundles": registered in the bundle class's build()
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new TestServicesPass());
    }

    /**
     * What an installation never has to write for this bundle: where its
     * entities are mapped, and where the versions that build its tables are.
     *
     * Both are prepended, so the bundle states its default and the
     * installation keeps the last word:
     *
     *   "As this method only prepends settings, any other settings done
     *    explicitly inside the config/* files would override these prepended
     *    settings."
     *
     * Each is guarded: an application may hold this bundle without the ORM or
     * without the migrations bundle, and must still boot.
     *
     * The migrations namespace is mapped to a lowercase directory by an
     * explicit prefix in composer.json; under the bundle's own prefix it would
     * resolve to nothing on a case-sensitive filesystem.
     *
     * @see https://symfony.com/doc/current/bundles/prepend_extension.html
     * @see https://symfony.com/bundles/DoctrineMigrationsBundle/current/index.html — "List of namespace/path pairs to search for migrations"
     * @see vendor/doctrine/doctrine-migrations-bundle/src/DependencyInjection/Configuration.php — the migrations_paths node
     */
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('doctrine')) {
            $builder->prependExtensionConfig('doctrine', [
                'orm' => [
                    'mappings' => [
                        'Identity' => [
                            'type' => 'attribute',
                            'dir' => __DIR__.'/Entity',
                            'prefix' => 'Vivutio\\Bundle\\IdentityBundle\\Entity',
                            'is_bundle' => false,
                        ],
                    ],
                ],
            ]);
        }

        if ($builder->hasExtension('doctrine_migrations')) {
            $builder->prependExtensionConfig('doctrine_migrations', [
                'migrations_paths' => [
                    'Vivutio\\Bundle\\IdentityBundle\\Migrations' => __DIR__.'/migrations',
                ],
            ]);
        }
    }

    /**
     * @param array<string, mixed> $config
     *
     * @see https://symfony.com/doc/current/bundles/extension.html — "In bundles
     *      extending the AbstractBundle class, you can define the
     *      loadExtension() method to load service definitions from
     *      configuration files"
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('config/services.php');
    }
}
