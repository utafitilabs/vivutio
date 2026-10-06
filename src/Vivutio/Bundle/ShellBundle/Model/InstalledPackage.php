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

namespace Vivutio\Bundle\ShellBundle\Model;

/** A vivutio package this installation has on disk: its name, version and own description. */
final readonly class InstalledPackage
{
    public function __construct(
        public string $name,
        public string $version,
        public string $description,
    ) {
    }
}
