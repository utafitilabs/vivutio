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
 * Every package's payments, for a module that keeps the books. The core
 * answers it; a module type-hints this.
 */
interface PaymentsInterface
{
    /**
     * Every payment, the earliest received first.
     *
     * @return list<Payment>
     */
    public function all(): array;

    /**
     * The payments against one charge, the earliest first.
     *
     * @return list<Payment>
     */
    public function against(string $chargeKey): array;
}
