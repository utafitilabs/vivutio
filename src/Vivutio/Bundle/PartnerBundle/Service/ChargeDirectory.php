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

namespace Vivutio\Bundle\PartnerBundle\Service;

use Vivutio\Contracts\Partner\Charge;
use Vivutio\Contracts\Partner\ChargesInterface;
use Vivutio\Contracts\Partner\ChargeSourceInterface;

/**
 * Every package's charges, gathered from the sources that say them.
 */
final readonly class ChargeDirectory implements ChargesInterface
{
    /**
     * @param iterable<ChargeSourceInterface> $sources
     */
    public function __construct(private iterable $sources)
    {
    }

    public function all(): array
    {
        $charges = [];
        foreach ($this->sources as $source) {
            foreach ($source->charges() as $charge) {
                $charges[] = $charge;
            }
        }
        usort($charges, static fn (Charge $a, Charge $b): int => [$a->dueOn, $a->reference] <=> [$b->dueOn, $b->reference]);

        return $charges;
    }

    public function find(string $key): ?Charge
    {
        foreach ($this->all() as $charge) {
            if ($charge->key === $key) {
                return $charge;
            }
        }

        return null;
    }
}
