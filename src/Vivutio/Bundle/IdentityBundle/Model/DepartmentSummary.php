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
use Vivutio\Bundle\IdentityBundle\Entity\User;

/**
 * A department as its register and record show it: who belongs, who
 * supports it, and who holds its head seat.
 */
final readonly class DepartmentSummary
{
    /**
     * @param list<User>  $members    who belong to it, the head first
     * @param list<User>  $supporters who support it from another department
     * @param string|null $sitsAt     the name of the place it sits at, null for the organization's own
     */
    public function __construct(
        public Department $department,
        public array $members,
        public array $supporters,
        public ?User $headHolder,
        public ?string $sitsAt = null,
    ) {
    }
}
