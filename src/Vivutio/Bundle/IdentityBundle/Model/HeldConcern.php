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

use Vivutio\Contracts\Access\Verb;

/**
 * One concern somebody holds right now, and the verbs they hold it with.
 */
final readonly class HeldConcern
{
    /**
     * @param list<Verb> $verbs in the order the six are listed
     */
    public function __construct(
        public string $declaredBy,
        public string $label,
        public bool $sensitive,
        public array $verbs,
    ) {
    }
}
