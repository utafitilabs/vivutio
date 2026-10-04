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

namespace Vivutio\Bundle\IdentityBundle\Model;

use Vivutio\Bundle\IdentityBundle\Entity\Department;
use Vivutio\Bundle\IdentityBundle\Entity\Office;
use Vivutio\Bundle\IdentityBundle\Entity\User;

/**
 * An office as its register and page show it: who is posted there and which
 * departments sit there.
 */
final readonly class OfficeSummary
{
    /**
     * @param list<User>       $posted
     * @param list<Department> $departments
     */
    public function __construct(
        public Office $office,
        public array $posted,
        public array $departments,
    ) {
    }
}
