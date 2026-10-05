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

namespace Vivutio\Contracts\Deletion;

/**
 * The record being deleted, as its owner names it: its kind ("position"),
 * the reference a Super Admin types to confirm ("Guide", an email address),
 * and its title.
 */
final readonly class DeletionSubject
{
    public function __construct(
        public string $kind,
        public string $reference,
        public string $title,
    ) {
    }
}
