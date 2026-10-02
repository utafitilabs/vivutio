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

use Vivutio\Bundle\IdentityBundle\Entity\User;

final class PasswordTooShortException extends \DomainException
{
    public function __construct(public readonly int $minimum = User::PASSWORD_MIN_LENGTH)
    {
        parent::__construct(\sprintf('A password must be at least %d characters.', $minimum));
    }
}
