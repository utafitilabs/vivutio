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
 * One concern's row of the matrix: a cell for each of the six verbs, holding
 * the pair a position may carry there, or null where there is no cell (the
 * concern does not declare the verb, or only the tiers hold it).
 */
final readonly class MatrixRow
{
    /**
     * @param array<string, string|null> $cells verb to its pair, in the six verbs' order
     */
    public function __construct(
        public string $label,
        public bool $sensitive,
        public array $cells,
    ) {
    }
}
