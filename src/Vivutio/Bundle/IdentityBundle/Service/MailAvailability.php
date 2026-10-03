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

namespace Vivutio\Bundle\IdentityBundle\Service;

use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * Whether this installation can send mail at all. A fresh installation has a
 * null transport until somebody sets MAILER_DSN, and a page that would send a
 * link says so rather than promising an email that will never arrive.
 */
final readonly class MailAvailability
{
    public function __construct(
        private bool $available,
    ) {
    }

    /**
     * A null transport names itself "null://".
     *
     * @see vendor/symfony/mailer/Transport/NullTransport.php — __toString()
     */
    public static function fromTransport(TransportInterface $transport): self
    {
        return new self(!str_starts_with((string) $transport, 'null://'));
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }
}
