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

namespace Vivutio\Bundle\IdentityBundle\Exception;

/**
 * Two accounts cannot answer to one address: it is what somebody signs in with.
 */
final class EmailAlreadyUsedException extends \DomainException
{
    public function __construct(public readonly string $email, ?\Throwable $previous = null)
    {
        parent::__construct(\sprintf('An account with the email %s already exists.', $email), 0, $previous);
    }
}
