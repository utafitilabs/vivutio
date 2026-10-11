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

namespace Vivutio\Bundle\PartnerBundle\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Vivutio\Bundle\PartnerBundle\Service\ChargeDirectory;
use Vivutio\Bundle\PartnerBundle\Service\PaymentDirectory;
use Vivutio\Contracts\Partner\Charge;
use Vivutio\Contracts\Partner\ChargeSourceInterface;
use Vivutio\Contracts\Partner\Payment;
use Vivutio\Contracts\Partner\PaymentSourceInterface;

/**
 * What is owed to the organization, gathered from every package that charges
 * for something: every source's charges, the soonest due first, each found
 * again by its key.
 */
final class ChargeDirectoryTest extends TestCase
{
    public function testEverySourcesChargesAreGatheredTheSoonestDueFirst(): void
    {
        $charges = new ChargeDirectory([
            self::source([self::charge('tour_booking:a', 'NCS-0002', '2026-11-05')]),
            self::source([self::charge('ticket:b', 'TK-0005', '2026-10-10'), self::charge('tour_booking:c', 'NCS-0001', '2026-11-05')]),
        ]);

        self::assertSame(['TK-0005', 'NCS-0001', 'NCS-0002'], array_map(static fn (Charge $charge): string => $charge->reference, $charges->all()));
        self::assertSame('NCS-0002', $charges->find('tour_booking:a')?->reference);
        self::assertNull($charges->find('tour_booking:z'));
    }

    public function testAChargeIsAWholeAmountInACurrency(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Charge('ticket:x', 'TK-0001', 'Ticket', 'DAR → ZNZ', null, 'Lukas Weber', 'usd', 21400, new \DateTimeImmutable('2026-11-01'), new \DateTimeImmutable('2026-11-01'));
    }

    /** Payments a package keeps, gathered the earliest first, those against one charge found by its key. */
    public function testEverySourcesPaymentsAreGathered(): void
    {
        $payments = new PaymentDirectory([new class implements PaymentSourceInterface {
            public function payments(): iterable
            {
                yield new Payment('payment:2', 'tour_booking:a', 'USD', 460858, new \DateTimeImmutable('2026-10-20'), 'Bank transfer', 'CRDB 88213999');
                yield new Payment('payment:1', 'tour_booking:a', 'USD', 300000, new \DateTimeImmutable('2026-10-10'), 'Bank transfer', 'CRDB 88213470');
                yield new Payment('payment:3', 'ticket:b', 'USD', 35600, new \DateTimeImmutable('2026-10-11'), 'Cash', '');
            }
        }]);

        self::assertSame(['payment:1', 'payment:3', 'payment:2'], array_map(static fn (Payment $payment): string => $payment->key, $payments->all()));
        self::assertSame(['payment:1', 'payment:2'], array_map(static fn (Payment $payment): string => $payment->key, $payments->against('tour_booking:a')));
    }

    /**
     * @param list<Charge> $charges
     */
    private static function source(array $charges): ChargeSourceInterface
    {
        return new class($charges) implements ChargeSourceInterface {
            /**
             * @param list<Charge> $charges
             */
            public function __construct(private readonly array $charges)
            {
            }

            public function charges(): iterable
            {
                return $this->charges;
            }
        };
    }

    private static function charge(string $key, string $reference, string $due): Charge
    {
        return new Charge($key, $reference, 'Tour booking', 'Northern Circuit Safari', null, 'Hansen family', 'USD', 760858, new \DateTimeImmutable('2026-10-06'), new \DateTimeImmutable($due));
    }
}
