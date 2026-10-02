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

use Vivutio\Bundle\IdentityBundle\Access\ConcernCatalogue;
use Vivutio\Bundle\IdentityBundle\Repository\PositionRepository;
use Vivutio\Bundle\IdentityBundle\Repository\UserRepository;
use Vivutio\Contracts\Access\ConcernSourceInterface;
use Vivutio\Contracts\Access\ScopeSourceInterface;

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
};
