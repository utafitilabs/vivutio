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

namespace Vivutio\Bundle\IdentityBundle\Place;

use Vivutio\Bundle\IdentityBundle\Entity\Office;
use Vivutio\Bundle\IdentityBundle\Repository\OfficeRepository;
use Vivutio\Contracts\Place\PlaceInterface;
use Vivutio\Contracts\Place\PlaceSourceInterface;

/**
 * The core's own places, its offices, offered through the tag a package
 * offers its places with.
 */
final readonly class OfficePlaces implements PlaceSourceInterface
{
    public function __construct(private OfficeRepository $offices)
    {
    }

    public function kind(): string
    {
        return Office::PLACE_KIND;
    }

    public function label(): string
    {
        return 'Offices';
    }

    public function places(): iterable
    {
        return $this->offices->findBy([], ['name' => 'ASC']);
    }

    public function find(string $id): ?PlaceInterface
    {
        return $this->offices->findOneBy(['uuid' => $id]);
    }
}
