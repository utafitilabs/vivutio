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

use Vivutio\Bundle\ShellBundle\Twig\DoorExtension;

/*
 * Every service is defined explicitly, with an id prefixed by the bundle's
 * alias; nothing is autowired or autoconfigured, so a tag is applied by hand.
 *
 * @see https://symfony.com/doc/current/bundles/best_practices.html#services
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    /*
     * door(), the one helper a template draws a control by. Tagged by hand:
     * the Twig bundle collects its extensions by this tag.
     *
     * @see vendor/symfony/twig-bundle/Resources/config/twig.php — ->tag('twig.extension')
     */
    $services->set('shell.twig.door', DoorExtension::class)
        ->args([service('security.authorization_checker')])
        ->tag('twig.extension');
};
