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

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Component\Console\Application;
use Vivutio\Bundle\IdentityBundle\Access\ConcernCatalogue;
use Vivutio\Bundle\IdentityBundle\Access\IdentityConcerns;
use Vivutio\Bundle\IdentityBundle\Command\CreateUserCommand;
use Vivutio\Bundle\IdentityBundle\Controller\SecurityController;
use Vivutio\Bundle\IdentityBundle\Controller\TeamController;
use Vivutio\Bundle\IdentityBundle\Repository\OrganizationRepository;
use Vivutio\Bundle\IdentityBundle\Repository\PositionRepository;
use Vivutio\Bundle\IdentityBundle\Repository\UserRepository;
use Vivutio\Bundle\IdentityBundle\Security\AccountVoter;
use Vivutio\Bundle\IdentityBundle\Security\ActiveUserChecker;
use Vivutio\Bundle\IdentityBundle\Security\GrantVoter;
use Vivutio\Bundle\IdentityBundle\Service\OrganizationService;
use Vivutio\Bundle\IdentityBundle\Service\PositionService;
use Vivutio\Bundle\IdentityBundle\Service\TeamDirectoryService;
use Vivutio\Bundle\IdentityBundle\Service\UserService;
use Vivutio\Contracts\Access\ConcernSourceInterface;
use Vivutio\Contracts\Access\ScopeSourceInterface;
use Vivutio\Contracts\Settings\OrganizationIdentitySourceInterface;

/*
 * Every service is defined explicitly, with an id prefixed by the bundle's
 * alias, and an alias from the class to that id for what an application may
 * type-hint. Nothing here is autowired or autoconfigured, so a tag a package
 * needs is one the package applies by hand.
 *
 *   "Services should not use autowiring or autoconfiguration. Instead, all
 *    services should be defined explicitly."
 *   "If the bundle defines services, they must be prefixed with the bundle
 *    alias instead of using fully qualified class names."
 *   "For public services, aliases should be created from the interface/class
 *    to the service id."
 *
 * @see https://symfony.com/doc/current/bundles/best_practices.html#services
 *
 * It is PHP so that no installation is made to carry a YAML parser for it, and
 * so that a class named here is one a refactoring and static analysis follow.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    /*
     * The tagged iterator is the whole of the seam: a core bundle's
     * declarations arrive through it exactly as a module's do, so nothing is
     * registered here on anybody's behalf.
     *
     *   "Symfony provides the tagged iterator shortcut for this, so you don't
     *    have to write a compiler pass for this common use case."
     *
     * @see https://symfony.com/doc/current/service_container/tags.html#reference-tagged-services
     * @see vendor/symfony/framework-bundle/Resources/config/asset_mapper.php — tagged_iterator('asset_mapper.compiler')
     */
    $services->set('identity.access.catalogue', ConcernCatalogue::class)
        ->args([
            tagged_iterator(ConcernSourceInterface::TAG),
            tagged_iterator(ScopeSourceInterface::TAG),
        ]);
    $services->alias(ConcernCatalogue::class, 'identity.access.catalogue');

    /*
     * What a position may grant about people. Declared like any package's,
     * through the tag the catalogue collects.
     */
    $services->set('identity.access.concerns', IdentityConcerns::class)
        ->tag(ConcernSourceInterface::TAG);

    /*
     * Every way an account comes into being or changes. The hasher is the
     * framework's own, the one a firewall verifies a password with, so an
     * account made here is one the installation's sign-in accepts.
     */
    $services->set('identity.accounts', UserService::class)
        ->args([
            service('doctrine.orm.entity_manager'),
            service('security.user_password_hasher'),
            service(UserRepository::class),
        ]);
    $services->alias(UserService::class, 'identity.accounts');

    /*
     * Every way a position comes into being or changes what it grants,
     * held to the pairs the catalogue offers a position.
     */
    $services->set('identity.positions', PositionService::class)
        ->args([
            service('doctrine.orm.entity_manager'),
            service('identity.access.catalogue'),
        ]);
    $services->alias(PositionService::class, 'identity.positions');

    /*
     * Whose installation this is: recorded on Settings, read by the frame and
     * by any module that prints the name, through the contract's service id.
     */
    $services->set('identity.organizations', OrganizationService::class)
        ->args([
            service('doctrine.orm.entity_manager'),
            service(OrganizationRepository::class),
        ]);
    $services->alias(OrganizationService::class, 'identity.organizations');
    $services->alias(OrganizationIdentitySourceInterface::SERVICE, 'identity.organizations');
    $services->alias(OrganizationIdentitySourceInterface::class, 'identity.organizations');

    /*
     * The rules between tiers, where Symfony's access decision manager asks
     * them. The tag is written by hand: the security bundle collects every
     * voter by it, and a reusable bundle is not autoconfigured.
     *
     * @see https://symfony.com/doc/current/security/voters.html
     * @see vendor/symfony/security-bundle/DependencyInjection/Compiler/AddSecurityVotersPass.php — findAndSortTaggedServices('security.voter')
     */
    $services->set('identity.voter.account', AccountVoter::class)
        ->args([service('identity.accounts')])
        ->tag('security.voter');

    /*
     * Every declared pair, `<concern>.<verb>`, the attribute a route's
     * #[IsGranted] and a control name.
     */
    $services->set('identity.voter.grant', GrantVoter::class)
        ->args([service('identity.access.catalogue')])
        ->tag('security.voter');

    /*
     * The one command the bundle contributes: the account an installation is
     * bootstrapped with.
     *
     * A bare tag, because the name and the description are on the class: the
     * compiler pass reads the #[AsCommand] attribute whether or not anything
     * was autoconfigured, and registers the service lazily under the name it
     * finds, which is how the framework's own commands are wired.
     *
     * Guarded on the component, as FrameworkBundle guards the file that
     * carries its own commands: a container compiled where there is no console
     * must not carry a service whose class it cannot load.
     *
     * @see https://symfony.com/doc/current/console.html#registering-the-command
     * @see vendor/symfony/console/DependencyInjection/AddConsoleCommandPass.php
     * @see vendor/symfony/framework-bundle/DependencyInjection/FrameworkExtension.php — hasConsole()
     */
    if (class_exists(Application::class)) {
        $services->set('identity.command.create_user', CreateUserCommand::class)
            ->args([service('identity.accounts')])
            ->tag('console.command');
    }

    /*
     * The installation's firewall names it as its user_checker: a deactivated
     * account is refused at the door, with the reason.
     */
    $services->set('identity.user_checker', ActiveUserChecker::class);

    /*
     * Drawing the sign-in form. Public, with an alias from its class, because
     * a route names the class and the resolver asks the container for it.
     *
     * @see vendor/symfony/framework-bundle/Resources/config/routing.php — TemplateController registered with its arguments and public
     */
    $services->set('identity.controller.security', SecurityController::class)
        ->args([
            service('twig'),
            service('security.authentication_utils'),
            service('security.token_storage'),
        ])
        ->public();
    $services->alias(SecurityController::class, 'identity.controller.security')->public();

    /*
     * The team list and its export.
     */
    $services->set('identity.team_directory', TeamDirectoryService::class)
        ->args([service(UserRepository::class)]);
    $services->alias(TeamDirectoryService::class, 'identity.team_directory');

    $services->set('identity.controller.team', TeamController::class)
        ->args([
            service('twig'),
            service('identity.team_directory'),
            service('security.authorization_checker'),
        ])
        ->public();
    $services->alias(TeamController::class, 'identity.controller.team')->public();

    /*
     * A repository keeps its class name as its id, the one place the bundle's
     * prefix cannot be used: the entity manager looks a repository up by class
     * name, and finds it among the services carrying this tag.
     *
     * @see vendor/doctrine/doctrine-bundle/src/Repository/ContainerRepositoryFactory.php
     * @see vendor/doctrine/doctrine-bundle/src/DependencyInjection/Compiler/ServiceRepositoryCompilerPass.php
     */
    $services->set(UserRepository::class)
        ->args([service('doctrine')])
        ->tag('doctrine.repository_service');
    $services->set(PositionRepository::class)
        ->args([service('doctrine')])
        ->tag('doctrine.repository_service');
    $services->set(OrganizationRepository::class)
        ->args([service('doctrine')])
        ->tag('doctrine.repository_service');
};
