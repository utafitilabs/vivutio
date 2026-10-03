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

namespace Vivutio\Core\Tests\Core\Authority;

/**
 * Every kind of person a route is asked by. Each is the one difference that
 * decides an outcome: no session at all, a session whose account was
 * deactivated after it began, a seat that grants nothing, a seat that grants
 * exactly what the route checks, a seat that grants everything else, and the
 * two tiers above the matrix.
 *
 * People placed in another department or posted elsewhere join when
 * departments and postings exist.
 */
enum Person: string
{
    case Stranger = 'signed out';
    case DeactivatedWhileSignedIn = 'deactivated while signed in';
    case StaffWithoutPosition = 'Staff, no position';
    case StaffHoldingThePair = 'Staff holding the pair';
    case StaffHoldingAllButThePair = 'Staff holding all but the pair';
    case Admin = 'Admin';
    case SuperAdmin = 'Super Admin';
}
