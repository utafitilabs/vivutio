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

use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * A "memory://" transport for the specifications: configured, so pages send
 * mail as they would in an installation, and delivering nowhere. What was
 * sent is read with the framework's own mailer assertions.
 *
 * @see https://symfony.com/doc/current/mailer.html#custom-transport-factories
 */
final class MemoryTransportFactory implements TransportFactoryInterface
{
    /**
     * The dispatcher a transport announces each message on: it is what renders
     * a templated email and what the mailer assertions read from.
     *
     * @see vendor/symfony/mailer/Transport/AbstractTransport.php — send() dispatches MessageEvent
     */
    public function __construct(
        private readonly ?EventDispatcherInterface $dispatcher = null,
    ) {
    }

    public function create(Dsn $dsn): TransportInterface
    {
        return new class($this->dispatcher) extends AbstractTransport {
            protected function doSend(SentMessage $message): void
            {
            }

            public function __toString(): string
            {
                return 'memory://';
            }
        };
    }

    public function supports(Dsn $dsn): bool
    {
        return 'memory' === $dsn->getScheme();
    }
}
