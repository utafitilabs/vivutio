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
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Vivutio\Bundle\IdentityBundle\Controller\SecurityController;
use Vivutio\Bundle\IdentityBundle\Entity\User;
use Vivutio\Contracts\Access\ConcernSourceInterface;
use Vivutio\Contracts\Access\ScopeSourceInterface;
use Vivutio\Core\Tests\Application\Fixtures\LandingController;
use Vivutio\Core\Tests\Application\Fixtures\NotesConcerns;
use Vivutio\Core\Tests\Application\Fixtures\NotesScopes;
use Vivutio\Core\Tests\Application\Fixtures\NoticesConcerns;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

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

    public const string SECURITY = 'test_public.security';

    public const string POSITIONS = 'test_public.positions';

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    /**
     * The core's own routes, mounted as an installation's recipe mounts them,
     * and this application's front page.
     */
    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@IdentityBundle/Controller/', 'attribute');
        $routes->import(__DIR__.'/Fixtures/LandingController.php', 'attribute');
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            // A session the browser in a specification can carry, and the
            // CSRF tokens the sign-in form is protected by.
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            'csrf_protection' => true,
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

        // What an installation writes in its own security.yaml; the core
        // ships none of it. The hasher's cost is the lowest the algorithm
        // allows, so a suite that creates accounts does not spend its time
        // hashing.
        //
        // @see https://symfony.com/doc/current/security/passwords.html
        $container->extension('security', [
            'password_hashers' => [
                PasswordAuthenticatedUserInterface::class => [
                    'algorithm' => 'auto',
                    'cost' => 4,
                    'time_cost' => 3,
                    'memory_cost' => 10,
                ],
            ],
            // No property: the provider asks the repository, which reads the
            // address in lowercase.
            'providers' => [
                'identity_user_provider' => [
                    'entity' => ['class' => User::class],
                ],
            ],
            'firewalls' => [
                'main' => [
                    'lazy' => true,
                    'provider' => 'identity_user_provider',
                    'user_checker' => 'identity.user_checker',
                    'form_login' => [
                        'login_path' => SecurityController::SIGN_IN,
                        'check_path' => SecurityController::SIGN_IN,
                        'enable_csrf' => true,
                        'default_target_path' => '/',
                    ],
                    'logout' => [
                        'path' => SecurityController::SIGN_OUT,
                        'target' => SecurityController::SIGN_IN,
                    ],
                    'remember_me' => [
                        'secret' => '%kernel.secret%',
                        'lifetime' => 604800,
                    ],
                ],
            ],
            // Closed by default: a stranger reaches sign-in and nothing else.
            'access_control' => [
                ['path' => '^/login', 'roles' => 'PUBLIC_ACCESS'],
                ['path' => '^/', 'roles' => 'ROLE_USER'],
            ],
        ]);

        $services = $container->services();

        // The framework's own hasher, made reachable: a specification proving
        // a stored password verifies uses the service a firewall does.
        $services->alias('test_public.hasher', 'security.user_password_hasher')->public();

        // The framework's own front to the access decision manager, made
        // reachable: a specification asks a question about a person exactly
        // as a page or a command would, and reads the reason it was refused.
        //
        // @see vendor/symfony/security-bundle/Security.php — getAccessDecisionForUser()
        $services->alias(self::SECURITY, 'security.helper')->public();

        // An installation provides a logger that writes to its own files. This
        // application has none to write to, and without one Symfony's default
        // logger prints every console event to the test run.
        $services->set('logger', NullLogger::class);

        $services->set(LandingController::class)
            ->args([service('security.token_storage')])
            ->public();

        // A package's declarations, tagged by hand as a reusable bundle tags
        // its own.
        $services->set('test.notes.concerns', NotesConcerns::class)
            ->tag(ConcernSourceInterface::TAG);
        $services->set('test.notes.scopes', NotesScopes::class)
            ->tag(ScopeSourceInterface::TAG);
        $services->set('test.notices.concerns', NoticesConcerns::class)
            ->tag(ConcernSourceInterface::TAG);

        // The catalogue, made reachable for the specifications to ask what
        // was declared.
        $services->alias(self::CATALOGUE, 'identity.access.catalogue')->public();

        // No screen writes a position yet, and a private service nothing
        // references is removed when the container compiles.
        $services->alias(self::POSITIONS, 'identity.positions')->public();
    }
}
