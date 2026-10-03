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
use Vivutio\Bundle\IdentityBundle\Entity\Organization;
use Vivutio\Bundle\IdentityBundle\Exception\InvalidOrganizationIdentityException;
use Vivutio\Bundle\IdentityBundle\Repository\OrganizationRepository;
use Vivutio\Contracts\Settings\OrganizationIdentity;
use Vivutio\Contracts\Settings\OrganizationIdentitySourceInterface;

/**
 * Records whose installation this is, and answers it to whoever asks.
 *
 * Recording again changes the one record; there is never a second. An empty
 * field is recorded as not set, and a value the screens could not draw is
 * refused before anything is written.
 */
final class OrganizationService implements OrganizationIdentitySourceInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    /**
     * @throws InvalidOrganizationIdentityException
     */
    public function record(string $name, ?string $shortName, ?string $timeZone, ?string $country): Organization
    {
        $name = trim($name);
        $shortName = self::orNull($shortName);
        $timeZone = self::orNull($timeZone);
        $country = self::orNull($country);

        if ('' === $name) {
            throw new InvalidOrganizationIdentityException('name', 'An organization is known by its name: it cannot be empty.');
        }
        if (mb_strlen($name) > Organization::NAME_MAX_LENGTH) {
            throw new InvalidOrganizationIdentityException('name', \sprintf('The name can be at most %d characters.', Organization::NAME_MAX_LENGTH));
        }
        if (null !== $shortName && mb_strlen($shortName) > Organization::SHORT_NAME_MAX_LENGTH) {
            throw new InvalidOrganizationIdentityException('short name', \sprintf('The short name can be at most %d characters; it is drawn where the full name will not fit.', Organization::SHORT_NAME_MAX_LENGTH));
        }
        if (null !== $timeZone && !\in_array($timeZone, \DateTimeZone::listIdentifiers(), true)) {
            throw new InvalidOrganizationIdentityException('time zone', \sprintf('"%s" is not a time zone this server knows; choose one from the list.', $timeZone));
        }
        if (null !== $country && mb_strlen($country) > Organization::COUNTRY_MAX_LENGTH) {
            throw new InvalidOrganizationIdentityException('country', \sprintf('The country can be at most %d characters.', Organization::COUNTRY_MAX_LENGTH));
        }

        $organization = $this->organizations->find(Organization::ONLY) ?? new Organization();
        $organization->setName($name)->setShortName($shortName)->setTimeZone($timeZone)->setCountry($country);

        $this->entityManager->persist($organization);
        $this->entityManager->flush();

        return $organization;
    }

    public function identity(): ?OrganizationIdentity
    {
        $organization = $this->organizations->find(Organization::ONLY);
        if (null === $organization) {
            return null;
        }

        return new OrganizationIdentity(
            name: $organization->getName(),
            shortName: $organization->getShortName(),
            timeZone: $organization->getTimeZone(),
            country: $organization->getCountry(),
        );
    }

    private static function orNull(?string $value): ?string
    {
        $value = null === $value ? '' : trim($value);

        return '' === $value ? null : $value;
    }
}
