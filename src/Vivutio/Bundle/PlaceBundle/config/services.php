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

use Vivutio\Bundle\PlaceBundle\Access\PlaceConcerns;
use Vivutio\Bundle\PlaceBundle\Controller\DestinationController;
use Vivutio\Bundle\PlaceBundle\Repository\DestinationFeeRepository;
use Vivutio\Bundle\PlaceBundle\Repository\DestinationRepository;
use Vivutio\Bundle\PlaceBundle\Service\DestinationFeeService;
use Vivutio\Bundle\PlaceBundle\Service\DestinationService;
use Vivutio\Bundle\PlaceBundle\Service\NightCostService;
use Vivutio\Contracts\Access\ConcernSourceInterface;
use Vivutio\Contracts\Stay\NightCostSourceInterface;

/*
 * Every service is defined explicitly, with an id prefixed by the bundle's
 * alias; nothing is autowired or autoconfigured, so a tag is applied by hand.
 *
 * @see https://symfony.com/doc/current/bundles/best_practices.html#services
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('place.access.concerns', PlaceConcerns::class)
        ->tag(ConcernSourceInterface::TAG);

    $services->set(DestinationRepository::class)
        ->args([service('doctrine')])
        ->tag('doctrine.repository_service');
    $services->set(DestinationFeeRepository::class)
        ->args([service('doctrine')])
        ->tag('doctrine.repository_service');

    $services->set('place.destinations', DestinationService::class)
        ->args([service('doctrine.orm.entity_manager'), service(DestinationRepository::class)]);
    $services->alias(DestinationService::class, 'place.destinations');

    // The fees charged at a destination, for touring to price a visit by.
    $services->set('place.destination_fees', DestinationFeeService::class)
        ->args([service('doctrine.orm.entity_manager')]);
    $services->alias(DestinationFeeService::class, 'place.destination_fees')->public();

    // What a night at a place costs, asked of the package that keeps the place,
    // for touring to cost a tour by.
    $services->set('place.night_costs', NightCostService::class)
        ->args([tagged_iterator(NightCostSourceInterface::TAG)]);
    $services->alias(NightCostService::class, 'place.night_costs')->public();

    $services->set('place.controller.destinations', DestinationController::class)
        ->args([
            service('twig'),
            service('place.destinations'),
            service('place.destination_fees'),
            service(DestinationRepository::class),
            service('security.csrf.token_manager'),
            service('router'),
        ])
        ->public();
    $services->alias(DestinationController::class, 'place.controller.destinations')->public();
};
