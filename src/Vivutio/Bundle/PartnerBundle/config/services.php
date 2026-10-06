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

use Vivutio\Bundle\PartnerBundle\Access\PartnerConcerns;
use Vivutio\Bundle\PartnerBundle\Channel\ManualChannel;
use Vivutio\Bundle\PartnerBundle\Controller\PartnerController;
use Vivutio\Bundle\PartnerBundle\Repository\PartnerRepository;
use Vivutio\Bundle\PartnerBundle\Service\PartnerDirectory;
use Vivutio\Bundle\PartnerBundle\Service\PartnerService;
use Vivutio\Bundle\PartnerBundle\Service\RoomNeedDirectory;
use Vivutio\Contracts\Access\ConcernSourceInterface;
use Vivutio\Contracts\Partner\PartnerChannelInterface;
use Vivutio\Contracts\Partner\PartnerDirectoryInterface;
use Vivutio\Contracts\Partner\RoomNeedsInterface;
use Vivutio\Contracts\Partner\RoomNeedSourceInterface;

/*
 * Every service is defined explicitly, with an id prefixed by the bundle's
 * alias; nothing is autowired or autoconfigured, so a tag is applied by hand.
 *
 * @see https://symfony.com/doc/current/bundles/best_practices.html#services
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('partner.access.concerns', PartnerConcerns::class)
        ->tag(ConcernSourceInterface::TAG);

    $services->set(PartnerRepository::class)
        ->args([service('doctrine')])
        ->tag('doctrine.repository_service');

    $services->set('partner.partners', PartnerService::class)
        ->args([service('doctrine.orm.entity_manager'), service(PartnerRepository::class)]);

    // What a module type-hints to offer partners and read back the one it stored.
    $services->set('partner.directory', PartnerDirectory::class)
        ->args([service(PartnerRepository::class)]);
    $services->alias(PartnerDirectoryInterface::class, 'partner.directory')->public();

    // Every package's room needs, for a module that requests rooms.
    $services->set('partner.room_needs', RoomNeedDirectory::class)
        ->args([tagged_iterator(RoomNeedSourceInterface::TAG)]);
    $services->alias(RoomNeedsInterface::class, 'partner.room_needs')->public();

    // How a module reaches a partner; the manual channel until the hub takes one over.
    $services->set('partner.channel.manual', ManualChannel::class)
        ->args([service('mailer')]);
    $services->alias(PartnerChannelInterface::class, 'partner.channel.manual')->public();

    $services->set('partner.controller.partners', PartnerController::class)
        ->args([
            service('twig'),
            service('partner.partners'),
            service(PartnerRepository::class),
            service('security.csrf.token_manager'),
            service('router'),
        ])
        ->public();
    $services->alias(PartnerController::class, 'partner.controller.partners')->public();
};
