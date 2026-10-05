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

/**
 * Where a delete page sits: the menu entry it lights, the trail to the
 * record, the record's Configure page to go back to, and the register to
 * land on once the record is gone.
 */
final readonly class DeletionPageContext
{
    /**
     * @param list<array{label: string, url: string}> $trail
     */
    public function __construct(
        public string $here,
        public array $trail,
        public string $back,
        public string $done,
    ) {
    }
}
