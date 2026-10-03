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
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Vivutio\Bundle\IdentityBundle\Entity\Trait\TimestampableTrait;
use Vivutio\Bundle\IdentityBundle\Entity\Trait\UuidTrait;
use Vivutio\Bundle\IdentityBundle\Enum\TierEnum;
use Vivutio\Bundle\IdentityBundle\Repository\UserRepository;

/**
 * The account somebody signs in with.
 *
 * It holds state and nothing else. Who may change a tier, deactivate an
 * account or act on it at all is decided where the change is made, never here.
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'identity_user')]
#[ORM\HasLifecycleCallbacks]
class User implements EquatableInterface, PasswordAuthenticatedUserInterface, UserInterface
{
    use TimestampableTrait;
    use UuidTrait;

    /** The fewest characters a password may have. */
    public const int PASSWORD_MIN_LENGTH = 12;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null; // @phpstan-ignore property.unusedType (assigned by Doctrine)

    #[ORM\Column(length: 180, unique: true)]
    private ?string $email = null;

    #[ORM\Column(length: 100)]
    private ?string $firstName = null;

    #[ORM\Column(length: 100)]
    private ?string $lastName = null;

    #[ORM\Column(length: 16, enumType: TierEnum::class)]
    private TierEnum $tier = TierEnum::Staff;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Position $position = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    /** Kept in lowercase: it is what somebody signs in with, and two spellings of one address are one person. */
    public function setEmail(string $email): static
    {
        $this->email = strtolower($email);

        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getFullName(): string
    {
        return trim($this->firstName.' '.$this->lastName);
    }

    public function getTier(): TierEnum
    {
        return $this->tier;
    }

    public function setTier(TierEnum $tier): static
    {
        $this->tier = $tier;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    /** The hash, never what was typed. */
    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getPosition(): ?Position
    {
        return $this->position;
    }

    public function setPosition(?Position $position): static
    {
        $this->position = $position;

        return $this;
    }

    /**
     * @return non-empty-string
     */
    public function getUserIdentifier(): string
    {
        $email = (string) $this->email;

        return '' !== $email ? $email : throw new \LogicException('An account with no address has nothing to sign in with.');
    }

    /**
     * A tier gives its standing and a position gives none. What Staff may do
     * is asked of their position each time, and is never turned into a role
     * that would grant the same authority again, coarsely, where nobody sees.
     *
     * @return list<string>
     */
    public function getRoles(): array
    {
        return match ($this->tier) {
            TierEnum::SuperAdmin => ['ROLE_USER', 'ROLE_SUPER_ADMIN', 'ROLE_ALLOWED_TO_SWITCH'],
            TierEnum::Admin => ['ROLE_USER', 'ROLE_ADMIN'],
            TierEnum::Staff => ['ROLE_USER'],
        };
    }

    /**
     * Whether the account a session holds is still this one. Asked on every
     * request with the account re-read from the database; when it answers no,
     * the session ends and its holder signs in again.
     *
     * Without it the framework compares only the address, the password and the
     * roles, and deactivating an account changes none of them: a person
     * deactivated while signed in would keep every page that checks no pair
     * until the session expired. So the comparison is taken over whole, and
     * keeps the framework's three while adding what decides standing.
     *
     * @see https://symfony.com/doc/current/security.html#comparing-users-manually-with-equatableinterface
     * @see vendor/symfony/security-http/Firewall/ContextListener.php — hasUserChanged() asks this first
     */
    public function isEqualTo(UserInterface $user): bool
    {
        return $user instanceof self
            && $user->getEmail() === $this->email
            && $user->getPassword() === $this->password
            && $user->getTier() === $this->tier
            && $user->isActive() === $this->active;
    }

    public function __toString(): string
    {
        return $this->getFullName();
    }
}
