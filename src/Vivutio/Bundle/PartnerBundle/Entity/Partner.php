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

namespace Vivutio\Bundle\PartnerBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Vivutio\Bundle\PartnerBundle\Enum\ChannelEnum;
use Vivutio\Bundle\PartnerBundle\Enum\PartnerKindEnum;
use Vivutio\Bundle\PartnerBundle\Enum\PartnerStatusEnum;
use Vivutio\Bundle\PartnerBundle\Repository\PartnerRepository;
use Vivutio\Contracts\Partner\PartnerInterface;

/**
 * An organization the installation's organization trades with: what it is to
 * it, who to write to, the terms it trades on and the channel a request
 * reaches it by. It never signs in.
 *
 * It holds state and nothing else.
 */
#[ORM\Entity(repositoryClass: PartnerRepository::class)]
#[ORM\Table(name: 'partner')]
class Partner implements PartnerInterface
{
    public const int NAME_MAX_LENGTH = 120;
    public const int CONTACT_MAX_LENGTH = 120;
    public const int PHONE_MAX_LENGTH = 40;
    public const int EMAIL_MAX_LENGTH = 180;
    public const int NOTES_MAX_LENGTH = 2000;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null; // @phpstan-ignore property.unusedType (assigned by Doctrine)

    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $uuid;

    #[ORM\Column(length: self::NAME_MAX_LENGTH, unique: true)]
    private string $name;

    #[ORM\Column(length: 24, enumType: PartnerKindEnum::class)]
    private PartnerKindEnum $kind;

    /** Its country, ISO 3166 alpha-2: "KE". */
    #[ORM\Column(length: 2)]
    private string $country;

    #[ORM\Column(length: self::EMAIL_MAX_LENGTH)]
    private string $email;

    /** Who to ask for there. */
    #[ORM\Column(length: self::CONTACT_MAX_LENGTH)]
    private string $contact = '';

    #[ORM\Column(length: self::PHONE_MAX_LENGTH)]
    private string $phone = '';

    /** The discount it trades at, a share to the cent. */
    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
    private string $discount = '0.00';

    /** The days it has to pay; 0 is on booking. */
    #[ORM\Column]
    private int $creditDays = 0;

    #[ORM\Column(length: 16, enumType: ChannelEnum::class)]
    private ChannelEnum $channel = ChannelEnum::Manual;

    #[ORM\Column(length: 16, enumType: PartnerStatusEnum::class)]
    private PartnerStatusEnum $status = PartnerStatusEnum::Active;

    #[ORM\Column(type: Types::TEXT)]
    private string $notes = '';

    public function __construct(string $name, PartnerKindEnum $kind, string $country, string $email)
    {
        $this->uuid = Uuid::v7();
        $this->name = $name;
        $this->kind = $kind;
        $this->country = $country;
        $this->email = $email;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): Uuid
    {
        return $this->uuid;
    }

    public function setUuid(Uuid $uuid): static
    {
        $this->uuid = $uuid;

        return $this;
    }

    public function getPartnerId(): string
    {
        return (string) $this->uuid;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getKind(): PartnerKindEnum
    {
        return $this->kind;
    }

    public function setKind(PartnerKindEnum $kind): static
    {
        $this->kind = $kind;

        return $this;
    }

    public function getPartnerKind(): string
    {
        return $this->kind->value;
    }

    public function getCountry(): string
    {
        return $this->country;
    }

    public function setCountry(string $country): static
    {
        $this->country = $country;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getContact(): string
    {
        return $this->contact;
    }

    public function setContact(string $contact): static
    {
        $this->contact = $contact;

        return $this;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getDiscount(): string
    {
        return $this->discount;
    }

    public function setDiscount(string $discount): static
    {
        $this->discount = $discount;

        return $this;
    }

    public function getCreditDays(): int
    {
        return $this->creditDays;
    }

    public function setCreditDays(int $creditDays): static
    {
        $this->creditDays = $creditDays;

        return $this;
    }

    public function getChannel(): ChannelEnum
    {
        return $this->channel;
    }

    public function getStatus(): PartnerStatusEnum
    {
        return $this->status;
    }

    public function setStatus(PartnerStatusEnum $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function isActive(): bool
    {
        return PartnerStatusEnum::Active === $this->status;
    }

    public function getNotes(): string
    {
        return $this->notes;
    }

    public function setNotes(string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }
}
