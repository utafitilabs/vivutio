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

namespace Vivutio\Bundle\IdentityBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Vivutio\Contracts\Access\ConcernSourceInterface;

/**
 * In a test container only, makes reachable what the authority test base asks
 * about: every voter, and every package's declarations. A module's suite then
 * needs no alias of its own to be held to the same proofs as the core's.
 *
 * Symfony's own test passes decide by the test services the framework
 * registers when `framework.test` is on; this one does the same, so an
 * installation's production container never holds these services.
 *
 * @see vendor/symfony/framework-bundle/DependencyInjection/Compiler/TestServiceContainerWeakRefPass.php — returns early without test.private_services_locator
 */
final class TestServicesPass implements CompilerPassInterface
{
    public const string VOTERS = 'identity.test.voters';

    public const string CONCERN_SOURCES = 'identity.test.concern_sources';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('test.private_services_locator')) {
            return;
        }

        $container->register(self::VOTERS, TaggedServices::class)
            ->setArguments([new TaggedIteratorArgument('security.voter')])
            ->setPublic(true);

        $container->register(self::CONCERN_SOURCES, TaggedServices::class)
            ->setArguments([new TaggedIteratorArgument(ConcernSourceInterface::TAG)])
            ->setPublic(true);
    }
}
