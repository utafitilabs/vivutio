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

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Vivutio\Contracts\Access\ConcernSourceInterface;
use Vivutio\Contracts\Access\ScopeSourceInterface;
use Vivutio\Core\Tests\Application\Fixtures\NotesConcerns;
use Vivutio\Core\Tests\Application\Fixtures\NotesScopes;

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

    public const string CATALOGUE = 'test.identity.access.catalogue';

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

        // One database for the whole core: the bundles are released together,
        // so their specifications never run against two schemas. The naming
        // strategy and identity columns are what an installation runs.
        //
        // @see https://symfony.com/doc/current/doctrine.html
        $container->extension('doctrine', [
            'dbal' => ['url' => '%env(VIVUTIO_TEST_DATABASE_URL)%'],
            'orm' => [
                'controller_resolver' => ['auto_mapping' => false],
                'naming_strategy' => 'doctrine.orm.naming_strategy.underscore',
                'identity_generation_preferences' => [
                    PostgreSQLPlatform::class => 'identity',
                ],
            ],
        ]);

        $services = $container->services();

        // An installation provides a logger that writes to its own files. This
        // application has none to write to, and without one Symfony's default
        // logger prints every console event to the test run.
        $services->set('logger', NullLogger::class);

        // A package's declarations, tagged by hand as a reusable bundle tags
        // its own.
        $services->set('test.notes.concerns', NotesConcerns::class)
            ->tag(ConcernSourceInterface::TAG);
        $services->set('test.notes.scopes', NotesScopes::class)
            ->tag(ScopeSourceInterface::TAG);

        // Nothing in this application references the catalogue yet, and a
        // private service nothing references is removed when the container
        // compiles. The alias keeps it for the specifications to ask.
        $services->alias(self::CATALOGUE, 'identity.access.catalogue')->public();
    }
}
