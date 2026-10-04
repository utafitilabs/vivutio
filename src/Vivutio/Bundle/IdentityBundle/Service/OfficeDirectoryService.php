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

use Vivutio\Bundle\IdentityBundle\Entity\Office;
use Vivutio\Bundle\IdentityBundle\Model\OfficeSummary;
use Vivutio\Bundle\IdentityBundle\Repository\DepartmentRepository;
use Vivutio\Bundle\IdentityBundle\Repository\OfficeRepository;
use Vivutio\Bundle\IdentityBundle\Repository\UserRepository;

/**
 * The offices as their pages read them.
 */
final readonly class OfficeDirectoryService
{
    public function __construct(
        private OfficeRepository $offices,
        private UserRepository $users,
        private DepartmentRepository $departments,
    ) {
    }

    /**
     * @return list<OfficeSummary> by name
     */
    public function summaries(): array
    {
        return array_map(fn (Office $office): OfficeSummary => $this->summary($office), $this->offices->findBy([], ['name' => 'ASC']));
    }

    public function summary(Office $office): OfficeSummary
    {
        return new OfficeSummary(
            $office,
            $this->users->findBy(['postedAt' => $office], ['firstName' => 'ASC', 'lastName' => 'ASC']),
            $this->departments->findBy(['office' => $office], ['name' => 'ASC']),
        );
    }
}
