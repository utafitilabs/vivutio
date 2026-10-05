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

use Vivutio\Bundle\ShellBundle\Access\SettingsConcerns;
use Vivutio\Bundle\ShellBundle\Access\ShellConcerns;
use Vivutio\Bundle\ShellBundle\Controller\DashboardController;
use Vivutio\Bundle\ShellBundle\EventListener\RefusalPage;
use Vivutio\Bundle\ShellBundle\Twig\DoorExtension;
use Vivutio\Bundle\ShellBundle\Twig\MenuExtension;
use Vivutio\Bundle\ShellBundle\Twig\OrganizationExtension;
use Vivutio\Contracts\Access\ConcernSourceInterface;
use Vivutio\Contracts\Settings\OrganizationIdentitySourceInterface;
use Vivutio\Contracts\Shell\MenuSourceInterface;

/*
 * Every service is defined explicitly, with an id prefixed by the bundle's
 * alias; nothing is autowired or autoconfigured, so a tag is applied by hand.
 *
 * @see https://symfony.com/doc/current/bundles/best_practices.html#services
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    /*
     * Who lands on the organization's dashboard, declared like any package's
     * permissions, through the tag the catalogue collects.
     */
    $services->set('shell.access.concerns', ShellConcerns::class)
        ->tag(ConcernSourceInterface::TAG);
    $services->set('shell.access.settings_concerns', SettingsConcerns::class)
        ->tag(ConcernSourceInterface::TAG);

    /*
     * Where everybody lands. Public, with an alias from its class, because a
     * route names the class and the resolver asks the container for it.
     *
     * @see vendor/symfony/framework-bundle/Resources/config/routing.php — TemplateController registered with its arguments and public
     */
    $services->set('shell.controller.dashboard', DashboardController::class)
        ->args([service('twig'), service('security.authorization_checker')])
        ->public();
    $services->alias(DashboardController::class, 'shell.controller.dashboard')->public();

    /*
     * The refusal page: after the firewall's own exception listener
     * (priority 1), before the framework renders its error page.
     *
     * @see vendor/symfony/security-http/Firewall/ExceptionListener.php — register(), priority 1
     */
    $services->set('shell.refusal_page', RefusalPage::class)
        ->args([service('twig')])
        ->tag('kernel.event_listener', ['event' => 'kernel.exception', 'method' => 'onException', 'priority' => 0]);

    /*
     * door(), the one helper a template draws a control by. Tagged by hand:
     * the Twig bundle collects its extensions by this tag.
     *
     * @see vendor/symfony/twig-bundle/Resources/config/twig.php — ->tag('twig.extension')
     */
    $services->set('shell.twig.door', DoorExtension::class)
        ->args([service('security.authorization_checker')])
        ->tag('twig.extension');

    // The pages packages put in the menu, collected by the tag the contract names.
    $services->set('shell.twig.menu', MenuExtension::class)
        ->args([tagged_iterator(MenuSourceInterface::TAG), service('security.authorization_checker'), service('router')])
        ->tag('twig.extension');

    $services->set('shell.twig.organization', OrganizationExtension::class)
        ->args([service(OrganizationIdentitySourceInterface::SERVICE)])
        ->tag('twig.extension');
};
