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
 * A count of what goes or stays, said for one and for many: "1 position",
 * "2 people, who keep their account without a seat".
 */
final readonly class DeletionLine
{
    public function __construct(
        public int $count,
        public string $one,
        public string $many,
    ) {
    }

    public function text(): string
    {
        return $this->count.' '.(1 === $this->count ? $this->one : $this->many);
    }
}
