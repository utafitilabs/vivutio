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

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Vivutio\Bundle\PlaceBundle\Enum\DestinationKindEnum;
use Vivutio\Bundle\PlaceBundle\Repository\DestinationRepository;

/**
 * A destination tours go to: a park, a reserve, a lake, a city. Those the
 * core ships carry the key the hub knows them by; an installation adds its
 * own. Its fees are kept beside it.
 *
 * It holds state and nothing else.
 */
#[ORM\Entity(repositoryClass: DestinationRepository::class)]
#[ORM\Table(name: 'place_destination')]
class Destination
{
    public const int NAME_MAX_LENGTH = 120;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null; // @phpstan-ignore property.unusedType (assigned by Doctrine)

    /** Its stable key, the country and the whole name: "tz-serengeti-national-park". */
    #[ORM\Column(name: '`key`', length: 64, unique: true)]
    private string $key;

    #[ORM\Column(length: self::NAME_MAX_LENGTH)]
    private string $name;

    #[ORM\Column(length: 24, enumType: DestinationKindEnum::class)]
    private DestinationKindEnum $kind;

    /** Its country, ISO 3166 alpha-2: "TZ". */
    #[ORM\Column(length: 2)]
    private string $country;

    /** Whether the core ships it, or the installation added it. */
    #[ORM\Column]
    private bool $shipped = false;

    /** @var Collection<int, DestinationFee> */
    #[ORM\OneToMany(targetEntity: DestinationFee::class, mappedBy: 'destination')]
    #[ORM\OrderBy(['validFrom' => 'ASC', 'id' => 'ASC'])]
    private Collection $fees;

    public function __construct(string $key, string $name, DestinationKindEnum $kind, string $country)
    {
        $this->key = $key;
        $this->name = $name;
        $this->kind = $kind;
        $this->country = $country;
        $this->fees = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getKind(): DestinationKindEnum
    {
        return $this->kind;
    }

    public function getCountry(): string
    {
        return $this->country;
    }

    public function isShipped(): bool
    {
        return $this->shipped;
    }

    /**
     * @return Collection<int, DestinationFee>
     */
    public function getFees(): Collection
    {
        return $this->fees;
    }
}
