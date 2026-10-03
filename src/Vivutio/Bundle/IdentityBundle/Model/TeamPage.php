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

use Vivutio\Bundle\IdentityBundle\Entity\User;

/**
 * One page of the team list, with the counts its filters show.
 *
 * The counts are over the whole team, never over the filtered list: a filter
 * reading "Admin 0" because somebody is searching for a name would be lying
 * about the organization.
 */
final readonly class TeamPage
{
    /**
     * @param list<User>                                          $people     the people on this page
     * @param int                                                 $total      how many match the query, on every page
     * @param int                                                 $everyone   how many people the organization has
     * @param array<string, int>|null                             $tierCounts tier value to how many hold it; null when the viewer sees no tiers
     * @param list<array{uuid: string, name: string, count: int}> $positions  every position held, by name, with how many hold it
     * @param int                                                 $noPosition how many hold no position
     * @param int                                                 $active     how many accounts are in use
     */
    public function __construct(
        public array $people,
        public int $total,
        public int $page,
        public int $perPage,
        public int $everyone,
        public ?array $tierCounts,
        public array $positions,
        public int $noPosition,
        public int $active,
    ) {
    }

    public function pages(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }

    public function deactivated(): int
    {
        return $this->everyone - $this->active;
    }

    /** One account, and it is the viewer's: there is nothing to filter. */
    public function isFirstRun(): bool
    {
        return 1 >= $this->everyone;
    }
}
