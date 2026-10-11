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

namespace Vivutio\Bundle\IdentityBundle\Enum;

/** What a line of the audit trail is about. */
enum AuditKindEnum: string
{
    case Account = 'account';
    case Position = 'position';
    case Department = 'department';
    case Settings = 'settings';
    case SignIn = 'sign_in';

    /** The kind as the log's filter names it, in the plural. */
    public function label(): string
    {
        return match ($this) {
            self::Account => 'Accounts',
            self::Position => 'Positions',
            self::Department => 'Departments',
            self::Settings => 'Settings',
            self::SignIn => 'Sign-ins',
        };
    }
}
