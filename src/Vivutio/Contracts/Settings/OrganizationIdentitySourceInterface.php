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

namespace Vivutio\Contracts\Settings;

/**
 * Whoever knows whose installation this is answers here; the core does, from
 * the organization's record.
 *
 * One service, not a tagged collection: an installation belongs to one
 * organization, and two sources answering would be two names on one page. A
 * module that prints the name, on a voucher or an invoice, asks this service
 * and never the core's tables.
 */
interface OrganizationIdentitySourceInterface
{
    public const string SERVICE = 'identity.organization_identity';

    /** Null until the organization is recorded: nothing invents a name. */
    public function identity(): ?OrganizationIdentity;
}
