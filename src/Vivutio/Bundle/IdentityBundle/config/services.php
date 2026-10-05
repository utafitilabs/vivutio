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
use Vivutio\Bundle\IdentityBundle\Controller\DepartmentController;
use Vivutio\Bundle\IdentityBundle\Controller\InvitationController;
use Vivutio\Bundle\IdentityBundle\Controller\OfficeController;
use Vivutio\Bundle\IdentityBundle\Controller\PasswordController;
use Vivutio\Bundle\IdentityBundle\Controller\PersonController;
use Vivutio\Bundle\IdentityBundle\Controller\PositionController;
use Vivutio\Bundle\IdentityBundle\Controller\SecurityController;
use Vivutio\Bundle\IdentityBundle\Controller\SettingsController;
use Vivutio\Bundle\IdentityBundle\Controller\TeamController;
use Vivutio\Bundle\IdentityBundle\Place\OfficePlaces;
use Vivutio\Bundle\IdentityBundle\Repository\AccountLinkRepository;
use Vivutio\Bundle\IdentityBundle\Repository\DepartmentRepository;
use Vivutio\Bundle\IdentityBundle\Repository\OfficeRepository;
use Vivutio\Bundle\IdentityBundle\Repository\OrganizationRepository;
use Vivutio\Bundle\IdentityBundle\Repository\PositionRepository;
use Vivutio\Bundle\IdentityBundle\Repository\UserRepository;
use Vivutio\Bundle\IdentityBundle\Security\AccountVoter;
use Vivutio\Bundle\IdentityBundle\Security\ActiveUserChecker;
use Vivutio\Bundle\IdentityBundle\Security\GrantVoter;
use Vivutio\Bundle\IdentityBundle\Service\AccountLinkService;
use Vivutio\Bundle\IdentityBundle\Service\DepartmentDirectoryService;
use Vivutio\Bundle\IdentityBundle\Service\DepartmentService;
use Vivutio\Bundle\IdentityBundle\Service\GrantsNowService;
use Vivutio\Bundle\IdentityBundle\Service\MailAvailability;
use Vivutio\Bundle\IdentityBundle\Service\OfficeDirectoryService;
use Vivutio\Bundle\IdentityBundle\Service\OfficeService;
use Vivutio\Bundle\IdentityBundle\Service\OrganizationService;
use Vivutio\Bundle\IdentityBundle\Service\PasswordRulesService;
use Vivutio\Bundle\IdentityBundle\Service\PlaceDirectoryService;
use Vivutio\Bundle\IdentityBundle\Service\PositionMatrixService;
use Vivutio\Bundle\IdentityBundle\Service\PositionService;
use Vivutio\Bundle\IdentityBundle\Service\TeamDirectoryService;
use Vivutio\Bundle\IdentityBundle\Service\UserService;
use Vivutio\Bundle\IdentityBundle\Twig\PlaceExtension;
use Vivutio\Contracts\Access\ConcernSourceInterface;
use Vivutio\Contracts\Access\ScopeSourceInterface;
use Vivutio\Contracts\Identity\PositionCardFieldInterface;
use Vivutio\Contracts\Place\PlaceSourceInterface;
use Vivutio\Contracts\Place\ReachSourceInterface;
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
            service(DepartmentRepository::class),
            service('identity.places'),
        ]);
    $services->alias(UserService::class, 'identity.accounts');

    $services->set('identity.departments', DepartmentService::class)
        ->args([
            service('doctrine.orm.entity_manager'),
            service(DepartmentRepository::class),
            service(UserRepository::class),
            service('identity.access.catalogue'),
            service('identity.places'),
        ]);
    $services->alias(DepartmentService::class, 'identity.departments');

    /*
     * Every place people are posted at: the core's offices, offered through
     * the tag any package offers its places with, and whatever a package
     * offers. The offices come first because they are registered first.
     */
    $services->set('identity.places', PlaceDirectoryService::class)
        ->args([tagged_iterator(PlaceSourceInterface::TAG)]);
    $services->alias(PlaceDirectoryService::class, 'identity.places');

    $services->set('identity.places.offices', OfficePlaces::class)
        ->args([service(OfficeRepository::class)])
        ->tag(PlaceSourceInterface::TAG, ['priority' => 100]);

    $services->set('identity.twig.places', PlaceExtension::class)
        ->args([service('identity.places')])
        ->tag('twig.extension');

    $services->set('identity.offices', OfficeService::class)
        ->args([service('doctrine.orm.entity_manager'), service(OfficeRepository::class)]);
    $services->alias(OfficeService::class, 'identity.offices');

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

    $services->set('identity.controller.settings', SettingsController::class)
        ->args([
            service('twig'),
            service('identity.organizations'),
            service('identity.organizations'),
            service('security.csrf.token_manager'),
            service('router'),
        ])
        ->public();
    $services->alias(SettingsController::class, 'identity.controller.settings')->public();

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
        ->args([service('identity.access.catalogue'), tagged_iterator(ReachSourceInterface::TAG)])
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

    /*
     * What somebody may do right now, asked of the voters through the
     * framework's own front to the access decision manager.
     *
     * @see vendor/symfony/security-bundle/Security.php — isGrantedForUser()
     */
    $services->set('identity.grants_now', GrantsNowService::class)
        ->args([service('identity.access.catalogue'), service('security.helper')]);

    $services->set('identity.controller.team', TeamController::class)
        ->args([
            service('twig'),
            service('identity.team_directory'),
            service('security.authorization_checker'),
            service('identity.grants_now'),
            tagged_iterator(PositionCardFieldInterface::TAG),
        ])
        ->public();
    $services->alias(TeamController::class, 'identity.controller.team')->public();

    $services->set('identity.controller.person', PersonController::class)
        ->args([
            service('twig'),
            service('identity.accounts'),
            service(PositionRepository::class),
            service('security.authorization_checker'),
            service('security.csrf.token_manager'),
            service('router'),
            service('identity.account_links'),
            service(AccountLinkRepository::class),
            service('identity.mail_availability'),
            service('identity.password_rules'),
            service(DepartmentRepository::class),
            service('identity.places'),
            tagged_iterator(PositionCardFieldInterface::TAG),
        ])
        ->public();
    $services->alias(PersonController::class, 'identity.controller.person')->public();

    $services->set('identity.position_matrix', PositionMatrixService::class)
        ->args([service('identity.access.catalogue'), service(UserRepository::class)]);

    $services->set('identity.controller.positions', PositionController::class)
        ->args([
            service('twig'),
            service(PositionRepository::class),
            service('identity.positions'),
            service('identity.position_matrix'),
            service('security.csrf.token_manager'),
            service('router'),
        ])
        ->public();
    $services->alias(PositionController::class, 'identity.controller.positions')->public();

    $services->set('identity.department_directory', DepartmentDirectoryService::class)
        ->args([service(DepartmentRepository::class), service(UserRepository::class), service(PositionRepository::class), service('identity.places')]);

    $services->set('identity.controller.departments', DepartmentController::class)
        ->args([
            service('twig'),
            service('identity.departments'),
            service('identity.department_directory'),
            service('identity.position_matrix'),
            service(PositionRepository::class),
            service('identity.places'),
            service('security.csrf.token_manager'),
            service('router'),
        ])
        ->public();
    $services->alias(DepartmentController::class, 'identity.controller.departments')->public();

    $services->set('identity.office_directory', OfficeDirectoryService::class)
        ->args([service(OfficeRepository::class), service(UserRepository::class), service(DepartmentRepository::class)]);

    $services->set('identity.controller.offices', OfficeController::class)
        ->args([
            service('twig'),
            service('identity.offices'),
            service('identity.office_directory'),
            service('security.csrf.token_manager'),
            service('router'),
        ])
        ->public();
    $services->alias(OfficeController::class, 'identity.controller.offices')->public();

    /*
     * Whether mail can be sent at all, read from the transport MAILER_DSN
     * configures: a null one means a fresh installation nobody has set up.
     *
     * @see vendor/symfony/framework-bundle/Resources/config/mailer.php — mailer.default_transport
     */
    $services->set('identity.mail_availability', MailAvailability::class)
        ->factory([MailAvailability::class, 'fromTransport'])
        ->args([service('mailer.default_transport')]);

    $services->set('identity.password_rules', PasswordRulesService::class);

    $services->set('identity.account_links', AccountLinkService::class)
        ->args([
            service('doctrine.orm.entity_manager'),
            service(AccountLinkRepository::class),
            service(UserRepository::class),
            service('mailer.mailer'),
            service('router'),
            service('security.user_password_hasher'),
            service('identity.password_rules'),
        ]);

    $services->set('identity.controller.password', PasswordController::class)
        ->args([
            service('twig'),
            service('identity.account_links'),
            service('identity.mail_availability'),
            service('security.csrf.token_manager'),
            service('router'),
            service('security.helper'),
        ])
        ->public();
    $services->alias(PasswordController::class, 'identity.controller.password')->public();

    $services->set('identity.controller.invitation', InvitationController::class)
        ->args([
            service('twig'),
            service('identity.account_links'),
            service('security.csrf.token_manager'),
            service('router'),
            service('security.helper'),
        ])
        ->public();
    $services->alias(InvitationController::class, 'identity.controller.invitation')->public();

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
    $services->set(AccountLinkRepository::class)
        ->args([service('doctrine')])
        ->tag('doctrine.repository_service');
    $services->set(DepartmentRepository::class)
        ->args([service('doctrine')])
        ->tag('doctrine.repository_service');
    $services->set(OfficeRepository::class)
        ->args([service('doctrine')])
        ->tag('doctrine.repository_service');
    $services->set(OrganizationRepository::class)
        ->args([service('doctrine')])
        ->tag('doctrine.repository_service');
};
