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

namespace Vivutio\Bundle\PlaceBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Intl\Countries;
use Vivutio\Bundle\PlaceBundle\Entity\Destination;
use Vivutio\Bundle\PlaceBundle\Enum\DestinationKindEnum;
use Vivutio\Bundle\PlaceBundle\Exception\InvalidDestinationException;
use Vivutio\Bundle\PlaceBundle\Repository\DestinationRepository;

/**
 * Destinations an installation adds beside those the core ships: named once
 * in their country, of a kind, in a country ISO 3166 knows, keyed by country
 * and name: "tz-lake-natron".
 */
final readonly class DestinationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DestinationRepository $destinations,
    ) {
    }

    /**
     * @throws InvalidDestinationException
     */
    public function create(string $name, string $kind, string $country): Destination
    {
        $name = trim($name);
        if ('' === $name) {
            throw new InvalidDestinationException('name', 'A destination is known by its name: it cannot be empty.');
        }
        if (mb_strlen($name) > Destination::NAME_MAX_LENGTH) {
            throw new InvalidDestinationException('name', \sprintf('A name can be at most %d characters.', Destination::NAME_MAX_LENGTH));
        }
        $kind = DestinationKindEnum::tryFrom($kind) ?? throw new InvalidDestinationException('kind', 'Choose what kind of place it is.');
        $country = strtoupper(trim($country));
        if (!Countries::exists($country)) {
            throw new InvalidDestinationException('country', 'Choose its country.');
        }
        foreach ($this->destinations->findBy(['country' => $country]) as $other) {
            if (mb_strtolower($other->getName()) === mb_strtolower($name)) {
                throw new InvalidDestinationException('name', \sprintf('%s has a destination called %s already.', Countries::getName($country), $other->getName()));
            }
        }

        $destination = new Destination(self::key($country, $name), $name, $kind, $country);
        $this->entityManager->persist($destination);
        $this->entityManager->flush();

        return $destination;
    }

    /** "tz-lake-natron": the country and the name, in lowercase letters and digits. */
    private static function key(string $country, string $name): string
    {
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', mb_strtolower((string) iconv('UTF-8', 'ASCII//TRANSLIT', $name))), '-');

        return mb_substr(strtolower($country).'-'.$slug, 0, 64);
    }
}
