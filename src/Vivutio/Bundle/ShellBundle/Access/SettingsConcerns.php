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
 * Settings: how the whole installation runs, read and changed by Super Admins
 * and Admins alone. Both pairs are the tiers' only, so no position can carry
 * them and the matrix draws no checkbox for them.
 */
final readonly class SettingsConcerns implements ConcernSourceInterface
{
    public const string SETTINGS = 'settings';

    public function declaredBy(): string
    {
        return 'Settings';
    }

    public function concerns(): iterable
    {
        yield new Concern(
            key: self::SETTINGS,
            label: 'Settings',
            description: 'How the whole installation runs, and whose it is: changed by the tiers alone.',
            verbs: [Verb::Read, Verb::Configure],
            scopes: [Scope::ORGANIZATION],
            tierOnly: [Verb::Read, Verb::Configure],
        );
    }
}
