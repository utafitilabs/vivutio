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

namespace Vivutio\Core\Tests\Application\Fixtures;

use Vivutio\Contracts\Access\Concern;
use Vivutio\Contracts\Access\ConcernSourceInterface;
use Vivutio\Contracts\Access\Scope;
use Vivutio\Contracts\Access\Verb;

/**
 * A concern that belongs to no module, played by an invented one: what a core
 * bundle declares about its own things. Configuring it is held by the tiers
 * alone, the shape the core gives to anything that confers power over people.
 */
final class NoticesConcerns implements ConcernSourceInterface
{
    public const string NOTICES = 'notices';

    public function declaredBy(): string
    {
        return 'Notices';
    }

    public function concerns(): iterable
    {
        yield new Concern(
            key: self::NOTICES,
            label: 'Notices',
            description: 'What is pinned to the organization\'s noticeboard.',
            verbs: [Verb::Read, Verb::Record, Verb::Configure],
            scopes: [Scope::ORGANIZATION],
            tierOnly: [Verb::Configure],
        );
    }
}
