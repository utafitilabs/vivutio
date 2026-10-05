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
use Vivutio\Bundle\IdentityBundle\Repository\DeletionRecordRepository;

/**
 * The line kept of a delete: when, by whom, what kind of record, its
 * reference and title, and what went with it. The record itself is gone; its
 * line names the deleter by name too, so it reads the same after they go.
 *
 * It holds state and nothing else.
 */
#[ORM\Entity(repositoryClass: DeletionRecordRepository::class)]
#[ORM\Table(name: 'identity_deletion')]
class DeletionRecord
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null; // @phpstan-ignore property.unusedType (assigned by Doctrine)

    /**
     * @param list<string> $whatWent
     */
    public function __construct(
        #[ORM\Column]
        private \DateTimeImmutable $deletedAt,
        #[ORM\Column(length: 160)]
        private string $byName,
        #[ORM\Column(nullable: true)]
        private ?int $byId,
        #[ORM\Column(length: 32)]
        private string $kind,
        #[ORM\Column(length: 180)]
        private string $reference,
        #[ORM\Column(length: 180)]
        private string $title,
        #[ORM\Column(type: Types::JSON)]
        private array $whatWent,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDeletedAt(): \DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function getByName(): string
    {
        return $this->byName;
    }

    public function getById(): ?int
    {
        return $this->byId;
    }

    public function getKind(): string
    {
        return $this->kind;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @return list<string>
     */
    public function getWhatWent(): array
    {
        return $this->whatWent;
    }
}
