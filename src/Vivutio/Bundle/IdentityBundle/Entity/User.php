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

    public const int NAME_MAX_LENGTH = 100;

    public const int PHONE_MAX_LENGTH = 32;

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

    /** A work number, in the reader's own format; a personal detail. */
    #[ORM\Column(length: self::PHONE_MAX_LENGTH, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 16, enumType: TierEnum::class)]
    private TierEnum $tier = TierEnum::Staff;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Position $position = null;

    /** False for somebody invited who has not yet named themselves and chosen a password. */
    #[ORM\Column(options: ['default' => true])]
    private bool $verified = true;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $invitedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $invitedBy = null;

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

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function isVerified(): bool
    {
        return $this->verified;
    }

    public function setVerified(bool $verified): static
    {
        $this->verified = $verified;

        return $this;
    }

    public function getInvitedAt(): ?\DateTimeImmutable
    {
        return $this->invitedAt;
    }

    public function getInvitedBy(): ?self
    {
        return $this->invitedBy;
    }

    public function setInvitation(\DateTimeImmutable $at, ?self $by): static
    {
        $this->invitedAt = $at;
        $this->invitedBy = $by;

        return $this;
    }

    /** The two letters drawn for a person, or a question mark until they have named themselves. */
    public function getInitials(): string
    {
        $initials = mb_strtoupper(mb_substr((string) $this->firstName, 0, 1).mb_substr((string) $this->lastName, 0, 1));

        return '' === $initials ? '?' : $initials;
    }

    /** Somebody invited names themselves on accepting; until then the account says so. */
    public function getFullName(): string
    {
        $name = trim($this->firstName.' '.$this->lastName);

        return '' === $name ? 'Not yet named' : $name;
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
            && $this->hasPasswordOf($user)
            && $user->getTier() === $this->tier
            && $user->isActive() === $this->active;
    }

    /**
     * What a session keeps of the account: everything but the password hash,
     * which is replaced by its checksum. A session store is a file or a cache
     * an installation may guard less closely than its database, and a checksum
     * cannot be cracked back into a password. A password changed since still
     * ends the session, because the checksum of the new hash differs.
     *
     * @return array<array-key, mixed> the object's properties as PHP casts them
     *
     * @see https://symfony.com/doc/current/security.html#understanding-how-users-are-refreshed-from-the-session
     * @see vendor/symfony/security-core/User/PasswordAuthenticatedUserInterface.php — the crc32c checksum, "the only algorithm supported"
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', (string) $this->password);

        return $data;
    }

    /**
     * Whether the other account has this one's password: the same hash, or,
     * when this one was read back from a session, the hash its checksum was
     * taken of. The same test the framework makes when it compares accounts
     * itself.
     *
     * @see vendor/symfony/security-http/Firewall/ContextListener.php — hasUserChanged()
     */
    private function hasPasswordOf(self $other): bool
    {
        $theirs = (string) $other->getPassword();

        return $theirs === $this->password
            || (8 === \strlen((string) $this->password) && hash('crc32c', $theirs) === $this->password);
    }

    public function __toString(): string
    {
        return $this->getFullName();
    }
}
