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
use Vivutio\Contracts\Place\PlaceSourceInterface;
use Vivutio\Contracts\Settings\OrganizationIdentitySourceInterface;
use Vivutio\Contracts\Shell\MenuSourceInterface;
use Vivutio\Core\Tests\Application\Fixtures\NoticesConcerns;
use Vivutio\Core\Tests\Application\NotesModule\NotesConcerns;
use Vivutio\Core\Tests\Application\NotesModule\NotesController;
use Vivutio\Core\Tests\Application\NotesModule\NotesMenu;
use Vivutio\Core\Tests\Application\NotesModule\NotesPlaces;
use Vivutio\Core\Tests\Application\NotesModule\NotesScopes;

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

    public const string ORGANIZATIONS = 'test_public.organizations';

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    /**
     * A kernel without debug never rebuilds its cache when a file changes, so
     * it keeps a folder of its own, which the specifications that run it
     * empty first: they judge the code as it is, never as it was compiled.
     */
    public function getCacheDir(): string
    {
        return $this->getProjectDir().'/var/cache/'.$this->environment.($this->debug ? '' : '_without_debug');
    }

    /**
     * The core's own routes, mounted as an installation mounts them.
     */
    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@IdentityBundle/Controller/', 'attribute');
        $routes->import('@ShellBundle/Controller/', 'attribute');

        // A stand-in module's pages, held to the proofs by a suite of its own.
        $routes->import(__DIR__.'/NotesModule/', 'attribute');
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
            // A configured transport that delivers nowhere; every message
            // goes from the address an installation sets in its headers.
            'mailer' => ['dsn' => 'memory://default', 'headers' => ['From' => 'vivutio <no-reply@vivutio-camps.example>']],
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

        $services->set(MemoryTransportFactory::class)->args([service('event_dispatcher')])->tag('mailer.transport_factory');

        // Recording the organization is reached by the Settings page; until
        // that page exists, a specification records it directly.
        $services->alias(self::ORGANIZATIONS, 'identity.organizations')->public();
        $services->alias('test_public.departments', 'identity.departments')->public();
        $services->alias('test_public.accounts', 'identity.accounts')->public();
        $services->alias('test_public.offices', 'identity.offices')->public();
        $services->alias('test_public.places', 'identity.places')->public();
        $services->alias('test_public.organization_identity', OrganizationIdentitySourceInterface::SERVICE)->public();

        // An installation provides a logger that writes to its own files. This
        // application has none to write to, and without one Symfony's default
        // logger prints every console event to the test run.
        $services->set('logger', NullLogger::class);

        // A package's declarations, tagged by hand as a reusable bundle tags
        // its own.
        $services->set('test.notes.concerns', NotesConcerns::class)
            ->tag(ConcernSourceInterface::TAG);
        $services->set(NotesController::class)->public();
        $services->set(NotesMenu::class)->tag(MenuSourceInterface::TAG);
        $services->set('test.notes.scopes', NotesScopes::class)
            ->tag(ScopeSourceInterface::TAG);
        $services->set('test.notes.places', NotesPlaces::class)
            ->tag(PlaceSourceInterface::TAG);
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
