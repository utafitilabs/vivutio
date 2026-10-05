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

namespace Vivutio\Bundle\PartnerBundle\Access;

use Vivutio\Contracts\Access\Concern;
use Vivutio\Contracts\Access\ConcernSourceInterface;
use Vivutio\Contracts\Access\Scope;
use Vivutio\Contracts\Access\Verb;

/**
 * What a position may grant about partners: reading who the organization
 * trades with and on what terms, and keeping that list.
 */
final readonly class PartnerConcerns implements ConcernSourceInterface
{
    public const string PARTNERS = 'partners';

    public function declaredBy(): string
    {
        return 'Partners';
    }

    public function concerns(): iterable
    {
        yield new Concern(
            key: self::PARTNERS,
            label: 'Partners',
            description: 'The organizations you trade with, who to write to there, and the terms they trade on.',
            verbs: [Verb::Read, Verb::Manage],
            scopes: [Scope::ORGANIZATION],
        );
    }
}
