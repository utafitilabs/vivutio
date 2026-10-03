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
use Vivutio\Bundle\IdentityBundle\Repository\OrganizationRepository;

/**
 * The organization this installation belongs to, one row and never a second:
 * its key is fixed, so a second insert is refused by the database itself.
 *
 * It holds state and nothing else; what a field may hold is decided where the
 * organization is recorded.
 */
#[ORM\Entity(repositoryClass: OrganizationRepository::class)]
#[ORM\Table(name: 'identity_organization')]
#[ORM\HasLifecycleCallbacks]
class Organization
{
    use TimestampableTrait;

    public const int ONLY = 1;

    public const int NAME_MAX_LENGTH = 160;

    public const int SHORT_NAME_MAX_LENGTH = 12;

    public const int COUNTRY_MAX_LENGTH = 80;

    #[ORM\Id]
    #[ORM\Column]
    private int $id = self::ONLY;

    #[ORM\Column(length: self::NAME_MAX_LENGTH)]
    private string $name = '';

    #[ORM\Column(length: self::SHORT_NAME_MAX_LENGTH, nullable: true)]
    private ?string $shortName = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $timeZone = null;

    #[ORM\Column(length: self::COUNTRY_MAX_LENGTH, nullable: true)]
    private ?string $country = null;

    public function getId(): int
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

    public function getShortName(): ?string
    {
        return $this->shortName;
    }

    public function setShortName(?string $shortName): static
    {
        $this->shortName = $shortName;

        return $this;
    }

    public function getTimeZone(): ?string
    {
        return $this->timeZone;
    }

    public function setTimeZone(?string $timeZone): static
    {
        $this->timeZone = $timeZone;

        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): static
    {
        $this->country = $country;

        return $this;
    }
}
