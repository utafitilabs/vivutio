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

namespace Vivutio\Contracts\Partner;

/**
 * The partners, for a module to offer and to read back by the id it stored.
 * The core answers it; a module type-hints this and never the core's tables.
 */
interface PartnerDirectoryInterface
{
    /**
     * The partners offered for new business, by name.
     *
     * @return list<PartnerInterface>
     */
    public function active(): array;

    /** Any partner, archived ones too, by the id a module stored; null when there is none. */
    public function find(string $partnerId): ?PartnerInterface;
}
