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
use Vivutio\Bundle\IdentityBundle\Enum\LinkPurposeEnum;
use Vivutio\Bundle\IdentityBundle\Repository\AccountLinkRepository;

/**
 * A link sent to somebody's address: to set a new password, or to accept an
 * invitation. It works once and until it expires.
 *
 * The link carries a selector, which finds the row, and a verifier, which only
 * its hash is kept of: somebody reading the table holds no working link, and
 * the verifier is compared in constant time.
 */
#[ORM\Entity(repositoryClass: AccountLinkRepository::class)]
#[ORM\Table(name: 'identity_account_link')]
class AccountLink
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null; // @phpstan-ignore property.unusedType (assigned by Doctrine)

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $account;

    #[ORM\Column(length: 16, enumType: LinkPurposeEnum::class)]
    private LinkPurposeEnum $purpose;

    #[ORM\Column(length: 32, unique: true)]
    private string $selector;

    #[ORM\Column(length: 64)]
    private string $verifierHash;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $usedAt = null;

    /** Who had it sent, or null when the holder asked for it themselves. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $sentBy;

    public function __construct(User $account, LinkPurposeEnum $purpose, string $selector, string $verifierHash, \DateTimeImmutable $now, ?User $sentBy)
    {
        $this->account = $account;
        $this->purpose = $purpose;
        $this->selector = $selector;
        $this->verifierHash = $verifierHash;
        $this->createdAt = $now;
        $this->expiresAt = $now->add($purpose->lifetime());
        $this->sentBy = $sentBy;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAccount(): User
    {
        return $this->account;
    }

    public function getPurpose(): LinkPurposeEnum
    {
        return $this->purpose;
    }

    public function getSelector(): string
    {
        return $this->selector;
    }

    public function getVerifierHash(): string
    {
        return $this->verifierHash;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getUsedAt(): ?\DateTimeImmutable
    {
        return $this->usedAt;
    }

    public function setUsedAt(\DateTimeImmutable $usedAt): static
    {
        $this->usedAt = $usedAt;

        return $this;
    }

    public function getSentBy(): ?User
    {
        return $this->sentBy;
    }
}
