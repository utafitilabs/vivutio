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
 * The change would leave the installation with no active Super Admin, and so
 * with nobody able to administer it and no way back in short of a console on
 * the server.
 *
 * Its message is also the instruction, because the refusal lifts the moment
 * somebody else is made a Super Admin: there is no owner flag and no spare
 * account, only the rule that the count may not reach zero.
 */
final class LastSuperAdminException extends \DomainException
{
    public static function cannotDemote(string $name): self
    {
        return new self(\sprintf('%s is the only active Super Admin, so their tier cannot be lowered: nobody would be left to administer the organization. Make somebody else a Super Admin first.', $name));
    }

    public static function cannotDeactivate(string $name): self
    {
        return new self(\sprintf('%s is the only active Super Admin, so the account cannot be deactivated: nobody would be left to administer the organization. Make somebody else a Super Admin, check they can sign in, then deactivate this account.', $name));
    }
}
