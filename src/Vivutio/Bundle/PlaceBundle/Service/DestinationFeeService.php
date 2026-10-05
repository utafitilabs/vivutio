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

namespace Vivutio\Bundle\PlaceBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Vivutio\Bundle\PlaceBundle\Entity\Destination;
use Vivutio\Bundle\PlaceBundle\Entity\DestinationFee;
use Vivutio\Bundle\PlaceBundle\Enum\FeeKindEnum;
use Vivutio\Bundle\PlaceBundle\Enum\FeePerEnum;
use Vivutio\Bundle\PlaceBundle\Enum\GuestEnum;
use Vivutio\Bundle\PlaceBundle\Enum\ResidencyEnum;
use Vivutio\Bundle\PlaceBundle\Exception\InvalidDestinationException;

/**
 * The fees charged at destinations. A fee is of a kind, for a guest and a
 * residency, per a person a day, a person an entry or a vehicle an entry, an
 * amount in a currency, from a first day to a last; two fees of the same
 * kind, guest, residency and per are never in force on the same day, so what
 * is charged is never in doubt.
 */
final readonly class DestinationFeeService
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /**
     * @param array<string, string> $typed the form's fields: kind, guest, residency, per, amount, currency, valid_from, valid_to
     *
     * @throws InvalidDestinationException
     */
    public function add(Destination $destination, array $typed): DestinationFee
    {
        $kind = FeeKindEnum::tryFrom($typed['kind'] ?? '') ?? throw new InvalidDestinationException('kind', 'Choose what the fee is for.');
        $guest = GuestEnum::tryFrom($typed['guest'] ?? '') ?? throw new InvalidDestinationException('guest', 'Choose who it is charged for.');
        $residency = ResidencyEnum::tryFrom($typed['residency'] ?? '') ?? throw new InvalidDestinationException('residency', 'Choose their residency.');
        $per = FeePerEnum::tryFrom($typed['per'] ?? '') ?? throw new InvalidDestinationException('per', 'Choose what it is charged per.');
        $amount = trim($typed['amount'] ?? '');
        if (1 !== preg_match('{^\d{1,9}(\.\d{1,2})?$}D', $amount)) {
            throw new InvalidDestinationException('amount', 'A fee is an amount of money, to the cent: 80 or 23.60.');
        }
        $currency = strtoupper(trim($typed['currency'] ?? ''));
        if (1 !== preg_match('{^[A-Z]{3}$}D', $currency)) {
            throw new InvalidDestinationException('currency', 'A currency is its three-letter code: USD, TZS, KES.');
        }
        $from = self::date($typed['valid_from'] ?? '', 'valid_from');
        $to = self::date($typed['valid_to'] ?? '', 'valid_to');
        if ($to < $from) {
            throw new InvalidDestinationException('valid_to', 'A fee is in force to its first day or later.');
        }

        foreach ($destination->getFees() as $other) {
            if ($other->getKind() === $kind && $other->getGuest() === $guest && $other->getResidency() === $residency && $other->getPer() === $per
                && $other->getValidFrom() <= $to && $other->getValidTo() >= $from) {
                throw new InvalidDestinationException('valid_from', \sprintf('A %s fee for %s %s %s is in force from %s to %s already.', mb_strtolower($kind->label()), mb_strtolower($residency->label()), mb_strtolower($guest->label()).'s', $per->label(), $other->getValidFrom()->format('j M Y'), $other->getValidTo()->format('j M Y')));
            }
        }

        $fee = new DestinationFee($destination, $kind, $guest, $residency, $per, number_format((float) $amount, 2, '.', ''), $currency, $from, $to);
        $destination->getFees()->add($fee);
        $this->entityManager->persist($fee);
        $this->entityManager->flush();

        return $fee;
    }

    public function remove(DestinationFee $fee): void
    {
        $fee->getDestination()->getFees()->removeElement($fee);
        $this->entityManager->remove($fee);
        $this->entityManager->flush();
    }

    /**
     * The fees charged at a destination on a day for a guest of a residency.
     *
     * @return list<DestinationFee>
     */
    public function charged(Destination $destination, \DateTimeImmutable $day, GuestEnum $guest, ResidencyEnum $residency): array
    {
        return array_values(array_filter($destination->getFees()->toArray(), static fn (DestinationFee $fee): bool => $fee->getGuest() === $guest && $fee->getResidency() === $residency && $fee->isInForce($day)));
    }

    /**
     * @throws InvalidDestinationException
     */
    private static function date(string $typed, string $field): \DateTimeImmutable
    {
        $typed = trim($typed);
        $date = 1 === preg_match('{^\d{4}-\d{2}-\d{2}$}D', $typed) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $typed) : false;
        if (false === $date || $date->format('Y-m-d') !== $typed) {
            throw new InvalidDestinationException($field, 'A date is a day of the calendar: 2026-07-01.');
        }

        return $date;
    }
}
