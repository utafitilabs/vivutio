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
use Vivutio\Bundle\IdentityBundle\Repository\PositionRepository;

/**
 * A seat in the organization: a name, and the pairs whoever sits in it holds.
 *
 * It holds state and nothing else. Which pairs a position may carry at all is
 * decided where a position is changed, never here: what is stored is what was
 * written, including a pair whose package has since been removed.
 */
#[ORM\Entity(repositoryClass: PositionRepository::class)]
#[ORM\Table(name: 'identity_position')]
#[ORM\HasLifecycleCallbacks]
class Position
{
    use TimestampableTrait;
    use UuidTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null; // @phpstan-ignore property.unusedType (assigned by Doctrine)

    #[ORM\Column(length: 120)]
    private ?string $name = null;

    /** @var list<string> each written "<concern>.<verb>" */
    #[ORM\Column(type: Types::JSON)]
    private array $grants = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getGrants(): array
    {
        return $this->grants;
    }

    /**
     * @param list<string> $grants
     */
    public function setGrants(array $grants): static
    {
        $this->grants = array_values(array_unique($grants));

        return $this;
    }

    public function grants(string $pair): bool
    {
        return \in_array($pair, $this->grants, true);
    }

    public function __toString(): string
    {
        return (string) $this->name;
    }
}
