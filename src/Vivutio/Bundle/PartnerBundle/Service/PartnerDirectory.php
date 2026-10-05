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

namespace Vivutio\Bundle\PartnerBundle\Service;

use Symfony\Component\Uid\Uuid;
use Vivutio\Bundle\PartnerBundle\Entity\Partner;
use Vivutio\Bundle\PartnerBundle\Enum\PartnerStatusEnum;
use Vivutio\Bundle\PartnerBundle\Repository\PartnerRepository;
use Vivutio\Contracts\Partner\PartnerDirectoryInterface;

/**
 * The partners as modules read them.
 */
final readonly class PartnerDirectory implements PartnerDirectoryInterface
{
    public function __construct(private PartnerRepository $partners)
    {
    }

    public function active(): array
    {
        return $this->partners->findBy(['status' => PartnerStatusEnum::Active], ['name' => 'ASC']);
    }

    public function find(string $partnerId): ?Partner
    {
        if (!Uuid::isValid($partnerId)) {
            return null;
        }

        return $this->partners->findOneBy(['uuid' => Uuid::fromString($partnerId)]);
    }
}
