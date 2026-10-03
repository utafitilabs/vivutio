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

namespace Vivutio\Bundle\IdentityBundle\Access;

use Vivutio\Contracts\Access\Concern;
use Vivutio\Contracts\Access\ConcernSourceInterface;
use Vivutio\Contracts\Access\Scope;
use Vivutio\Contracts\Access\Verb;

/**
 * What a position may grant about the people of the organization.
 *
 * Knowing that somebody is on the team and knowing how to reach them are two
 * concerns, so that an organization can open the one and keep the other: the
 * directory is who is here, personal details are how to reach them.
 *
 * Only what some route or control checks is declared. A verb is added with
 * the page that enforces it, because a box in the matrix that nothing checks
 * changes nothing when it is ticked, and the route walk refuses it.
 *
 * They reach the organization. A department's people become a scope of their
 * own when departments are stored.
 */
final readonly class IdentityConcerns implements ConcernSourceInterface
{
    public const string DIRECTORY = 'directory';
    public const string PERSONAL_DETAILS = 'personal_details';

    public function declaredBy(): string
    {
        return 'Team';
    }

    public function concerns(): iterable
    {
        yield new Concern(
            key: self::DIRECTORY,
            label: 'Directory',
            description: 'Who is on the team: their name, their position and whether their account is in use.',
            verbs: [Verb::Read, Verb::Export],
            scopes: [Scope::ORGANIZATION],
        );

        yield new Concern(
            key: self::PERSONAL_DETAILS,
            label: 'Personal details',
            description: 'How to reach a person: their address and contact details, distinct from knowing they are on the team.',
            verbs: [Verb::Read],
            scopes: [Scope::ORGANIZATION],
            sensitive: true,
        );
    }
}
