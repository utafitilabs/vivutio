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
use Vivutio\Bundle\IdentityBundle\Enum\AuditKindEnum;
use Vivutio\Bundle\IdentityBundle\Repository\AuditEventRepository;

/**
 * A line of the audit trail: when, what kind, what was done in words a person
 * reads and the names in them read in bold, who did it (by name, as they were
 * called then, and their uuid) and the address it came from. Written at the
 * moment of the change and never changed after; nobody is a foreign key, so a
 * deleted person's lines stay.
 *
 * It holds state and nothing else.
 */
#[ORM\Entity(repositoryClass: AuditEventRepository::class)]
#[ORM\Table(name: 'identity_audit_event')]
#[ORM\Index(columns: ['at'])]
class AuditEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null; // @phpstan-ignore property.unusedType (assigned by Doctrine)

    public function __construct(
        #[ORM\Column]
        private \DateTimeImmutable $at,
        #[ORM\Column(length: 16, enumType: AuditKindEnum::class)]
        private AuditKindEnum $kind,
        #[ORM\Column(type: Types::TEXT)]
        private string $said,
        /** @var list<string> what the line is about, read in bold */
        #[ORM\Column(type: Types::JSON)]
        private array $named = [],
        #[ORM\Column(length: 120, nullable: true)]
        private ?string $actorName = null,
        #[ORM\Column(length: 36, nullable: true)]
        private ?string $actorId = null,
        #[ORM\Column(length: 45, nullable: true)]
        private ?string $address = null,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAt(): \DateTimeImmutable
    {
        return $this->at;
    }

    public function getKind(): AuditKindEnum
    {
        return $this->kind;
    }

    public function getSaid(): string
    {
        return $this->said;
    }

    /** @return list<string> */
    public function getNamed(): array
    {
        return $this->named;
    }

    public function getActorName(): ?string
    {
        return $this->actorName;
    }

    public function getActorId(): ?string
    {
        return $this->actorId;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }
}
