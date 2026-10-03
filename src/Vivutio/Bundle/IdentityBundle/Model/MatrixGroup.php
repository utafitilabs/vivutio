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
 * The rows one package declares, drawn together under its name.
 */
final readonly class MatrixGroup
{
    /**
     * @param list<MatrixRow> $rows
     */
    public function __construct(
        public string $declaredBy,
        public array $rows,
    ) {
    }
}
