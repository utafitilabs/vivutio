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

namespace Vivutio\Bundle\IdentityBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Vivutio\Bundle\IdentityBundle\Entity\Trait\TimestampableTrait;
use Vivutio\Bundle\IdentityBundle\Entity\Trait\UuidTrait;
use Vivutio\Bundle\IdentityBundle\Repository\OfficeRepository;
use Vivutio\Contracts\Place\PlaceInterface;

/**
 * An office: a place of the organization's own, in a real city, where people
 * are posted and departments may sit. A property is a module's place, never
 * an office.
 *
 * It holds state and nothing else.
 */
#[ORM\Entity(repositoryClass: OfficeRepository::class)]
#[ORM\Table(name: 'identity_office')]
#[ORM\HasLifecycleCallbacks]
class Office implements PlaceInterface
{
    use TimestampableTrait;
    use UuidTrait;
    public const string PLACE_KIND = 'office';

    public const int NAME_MAX_LENGTH = 120;

    public const int CITY_MAX_LENGTH = 120;

    public const int COUNTRY_MAX_LENGTH = 80;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null; // @phpstan-ignore property.unusedType (assigned by Doctrine)

    #[ORM\Column(length: self::NAME_MAX_LENGTH)]
    private string $name = '';

    #[ORM\Column(length: self::CITY_MAX_LENGTH)]
    private string $city = '';

    #[ORM\Column(length: self::COUNTRY_MAX_LENGTH)]
    private string $country = '';

    public function getId(): ?int
    {
        return $this->id;
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

    public function getCity(): string
    {
        return $this->city;
    }

    public function setCity(string $city): static
    {
        $this->city = $city;

        return $this;
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

    public function __toString(): string
    {
        return $this->name;
    }

    public function getPlaceKind(): string
    {
        return self::PLACE_KIND;
    }

    public function getPlaceId(): string
    {
        return (string) $this->getUuid();
    }
}
