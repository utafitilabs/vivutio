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

namespace Vivutio\Bundle\IdentityBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Vivutio\Bundle\IdentityBundle\Entity\Office;
use Vivutio\Bundle\IdentityBundle\Exception\InvalidOfficeException;
use Vivutio\Bundle\IdentityBundle\Repository\OfficeRepository;

/**
 * Offices: named once, each in a city and a country.
 */
final readonly class OfficeService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private OfficeRepository $offices,
    ) {
    }

    /**
     * @throws InvalidOfficeException
     */
    public function create(string $name, string $city, string $country): Office
    {
        $office = new Office();
        $this->fill($office, $name, $city, $country);

        $this->entityManager->persist($office);
        $this->entityManager->flush();

        return $office;
    }

    /**
     * @throws InvalidOfficeException
     */
    public function change(Office $office, string $name, string $city, string $country): void
    {
        $this->fill($office, $name, $city, $country);
        $this->entityManager->flush();
    }

    /**
     * @throws InvalidOfficeException
     */
    private function fill(Office $office, string $name, string $city, string $country): void
    {
        $values = ['name' => [trim($name), 'name', Office::NAME_MAX_LENGTH], 'city' => [trim($city), 'city', Office::CITY_MAX_LENGTH], 'country' => [trim($country), 'country', Office::COUNTRY_MAX_LENGTH]];

        foreach ($values as $field => [$value, $words, $max]) {
            if ('' === $value) {
                throw new InvalidOfficeException($field, \sprintf('An office needs its %s.', $words));
            }
            if (mb_strlen($value) > $max) {
                throw new InvalidOfficeException($field, \sprintf('A %s can be at most %d characters.', $words, $max));
            }
        }

        foreach ($this->offices->findAll() as $other) {
            if ($other !== $office && mb_strtolower($other->getName()) === mb_strtolower($values['name'][0])) {
                throw new InvalidOfficeException('name', \sprintf('There is already an office called %s.', $other->getName()));
            }
        }

        $office->setName($values['name'][0])->setCity($values['city'][0])->setCountry($values['country'][0]);
    }
}
