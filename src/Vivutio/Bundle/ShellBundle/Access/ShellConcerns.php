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

namespace Vivutio\Bundle\ShellBundle\Access;

use Vivutio\Contracts\Access\Concern;
use Vivutio\Contracts\Access\ConcernSourceInterface;
use Vivutio\Contracts\Access\Scope;
use Vivutio\Contracts\Access\Verb;

/**
 * What a position may grant about the shell: who lands on the organization's
 * dashboard. It is a grant of its own because no other permission can say it:
 * a reading of anything else is held by people who should land on their own.
 */
final readonly class ShellConcerns implements ConcernSourceInterface
{
    public const string DASHBOARD = 'dashboard';

    public function declaredBy(): string
    {
        return 'Dashboard';
    }

    public function concerns(): iterable
    {
        yield new Concern(
            key: self::DASHBOARD,
            label: 'Organization dashboard',
            description: 'The organization\'s dashboard as the page one lands on; everybody else lands on their own.',
            verbs: [Verb::Read],
            scopes: [Scope::ORGANIZATION],
        );
    }
}
