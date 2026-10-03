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

use Vivutio\Bundle\IdentityBundle\Entity\Position;
use Vivutio\Bundle\IdentityBundle\Entity\User;

/**
 * A position as the register lists it: what it grants, counted by verb, how
 * many of those concerns are sensitive, and who holds it.
 */
final readonly class PositionSummary
{
    /**
     * @param array<string, int> $concernsByVerb verb to the number of concerns granted with it; verbs with none left out
     * @param list<User>         $holders
     */
    public function __construct(
        public Position $position,
        public array $concernsByVerb,
        public int $sensitive,
        public array $holders,
    ) {
    }
}
