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

use Vivutio\Contracts\Partner\Payment;
use Vivutio\Contracts\Partner\PaymentsInterface;
use Vivutio\Contracts\Partner\PaymentSourceInterface;

/**
 * Every package's payments, gathered from the sources that say them.
 */
final readonly class PaymentDirectory implements PaymentsInterface
{
    /**
     * @param iterable<PaymentSourceInterface> $sources
     */
    public function __construct(private iterable $sources)
    {
    }

    public function all(): array
    {
        $payments = [];
        foreach ($this->sources as $source) {
            foreach ($source->payments() as $payment) {
                $payments[] = $payment;
            }
        }
        usort($payments, static fn (Payment $a, Payment $b): int => [$a->receivedOn, $a->key] <=> [$b->receivedOn, $b->key]);

        return $payments;
    }

    public function against(string $chargeKey): array
    {
        return array_values(array_filter($this->all(), static fn (Payment $payment): bool => $payment->chargeKey === $chargeKey));
    }
}
