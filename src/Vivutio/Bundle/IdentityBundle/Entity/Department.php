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

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Vivutio\Bundle\IdentityBundle\Entity\Trait\TimestampableTrait;
use Vivutio\Bundle\IdentityBundle\Entity\Trait\UuidTrait;
use Vivutio\Bundle\IdentityBundle\Repository\DepartmentRepository;
use Vivutio\Contracts\Place\PlaceInterface;

/**
 * A department: the one place somebody reports to. It is named by what it
 * does, headed by a position, and allows the modules' pairs its people may
 * hold; a new one allows nothing.
 *
 * It holds state and nothing else. Who may head it and what it may allow are
 * decided where it is changed.
 */
#[ORM\Entity(repositoryClass: DepartmentRepository::class)]
#[ORM\Table(name: 'identity_department')]
#[ORM\HasLifecycleCallbacks]
class Department
{
    use TimestampableTrait;
    use UuidTrait;

    public const int NAME_MAX_LENGTH = 120;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null; // @phpstan-ignore property.unusedType (assigned by Doctrine)

    #[ORM\Column(length: self::NAME_MAX_LENGTH)]
    private string $name = '';

    /** The kind of place it sits at, an office or a package's place, or null for the organization's own. */
    #[ORM\Column(length: 32, nullable: true)]
    private ?string $placeKind = null;

    /** That place's id within its kind. */
    #[ORM\Column(type: Types::GUID, nullable: true)]
    private ?string $placeId = null;

    /** The position whose one holder heads it; a position heads one department at most. */
    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: true, unique: true, onDelete: 'SET NULL')]
    private ?Position $head = null;

    /** @var list<string> each a module's pair, "<concern>.<verb>" */
    #[ORM\Column(type: Types::JSON)]
    private array $allows = [];

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

    public function getPlaceKind(): ?string
    {
        return $this->placeKind;
    }

    public function getPlaceId(): ?string
    {
        return $this->placeId;
    }

    public function setPlace(?PlaceInterface $place): static
    {
        $this->placeKind = $place?->getPlaceKind();
        $this->placeId = $place?->getPlaceId();

        return $this;
    }

    /** Whether it sits at this place; null asks whether it is the organization's own. */
    public function sitsAt(?PlaceInterface $place): bool
    {
        return null === $place
            ? null === $this->placeKind
            : $this->placeKind === $place->getPlaceKind() && $this->placeId === $place->getPlaceId();
    }

    /** Whether somebody may belong to it or support it from where they are posted: it is the organization's, or sits there. */
    public function isReachableFrom(User $user): bool
    {
        return null === $this->placeKind || ($this->placeKind === $user->getPostedKind() && $this->placeId === $user->getPostedId());
    }

    public function getHead(): ?Position
    {
        return $this->head;
    }

    public function setHead(?Position $head): static
    {
        $this->head = $head;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getAllows(): array
    {
        return $this->allows;
    }

    /**
     * @param list<string> $allows
     */
    public function setAllows(array $allows): static
    {
        $this->allows = $allows;

        return $this;
    }

    public function allows(string $pair): bool
    {
        return \in_array($pair, $this->allows, true);
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
