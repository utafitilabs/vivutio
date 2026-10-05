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

namespace Vivutio\Bundle\PlaceBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Vivutio\Bundle\PlaceBundle\Enum\FeeKindEnum;
use Vivutio\Bundle\PlaceBundle\Enum\FeePerEnum;
use Vivutio\Bundle\PlaceBundle\Enum\GuestEnum;
use Vivutio\Bundle\PlaceBundle\Enum\ResidencyEnum;
use Vivutio\Bundle\PlaceBundle\Repository\DestinationFeeRepository;

/**
 * A fee charged at a destination: its kind, for which guest and residency,
 * what it is per, how much in which currency, from a first day to a last.
 *
 * It holds state and nothing else.
 */
#[ORM\Entity(repositoryClass: DestinationFeeRepository::class)]
#[ORM\Table(name: 'place_destination_fee')]
class DestinationFee
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null; // @phpstan-ignore property.unusedType (assigned by Doctrine)

    #[ORM\ManyToOne(inversedBy: 'fees')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Destination $destination;

    #[ORM\Column(length: 16, enumType: FeeKindEnum::class)]
    private FeeKindEnum $kind;

    #[ORM\Column(length: 16, enumType: GuestEnum::class)]
    private GuestEnum $guest;

    #[ORM\Column(length: 16, enumType: ResidencyEnum::class)]
    private ResidencyEnum $residency;

    #[ORM\Column(length: 16, enumType: FeePerEnum::class)]
    private FeePerEnum $per;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $amount;

    #[ORM\Column(length: 3)]
    private string $currency;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $validFrom;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $validTo;

    public function __construct(Destination $destination, FeeKindEnum $kind, GuestEnum $guest, ResidencyEnum $residency, FeePerEnum $per, string $amount, string $currency, \DateTimeImmutable $validFrom, \DateTimeImmutable $validTo)
    {
        $this->destination = $destination;
        $this->kind = $kind;
        $this->guest = $guest;
        $this->residency = $residency;
        $this->per = $per;
        $this->amount = $amount;
        $this->currency = $currency;
        $this->validFrom = $validFrom;
        $this->validTo = $validTo;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDestination(): Destination
    {
        return $this->destination;
    }

    public function getKind(): FeeKindEnum
    {
        return $this->kind;
    }

    public function getGuest(): GuestEnum
    {
        return $this->guest;
    }

    public function getResidency(): ResidencyEnum
    {
        return $this->residency;
    }

    public function getPer(): FeePerEnum
    {
        return $this->per;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getValidFrom(): \DateTimeImmutable
    {
        return $this->validFrom;
    }

    public function getValidTo(): \DateTimeImmutable
    {
        return $this->validTo;
    }

    public function isInForce(\DateTimeImmutable $day): bool
    {
        return $this->validFrom <= $day && $this->validTo >= $day;
    }
}
