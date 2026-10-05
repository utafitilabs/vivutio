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

namespace Vivutio\Bundle\PlaceBundle\Access;

use Vivutio\Contracts\Access\Concern;
use Vivutio\Contracts\Access\ConcernSourceInterface;
use Vivutio\Contracts\Access\Scope;
use Vivutio\Contracts\Access\Verb;

/**
 * What a position may grant about destinations: reading them and their fees.
 * Adding a destination and setting its fees is the tiers' alone, since tours
 * are priced from them.
 */
final readonly class PlaceConcerns implements ConcernSourceInterface
{
    public const string DESTINATIONS = 'destinations';

    public function declaredBy(): string
    {
        return 'Destinations';
    }

    public function concerns(): iterable
    {
        yield new Concern(
            key: self::DESTINATIONS,
            label: 'Destinations',
            description: 'The parks, reserves and other places tours go to, and the fees charged at each.',
            verbs: [Verb::Read, Verb::Configure],
            scopes: [Scope::ORGANIZATION],
            tierOnly: [Verb::Configure],
        );
    }
}
