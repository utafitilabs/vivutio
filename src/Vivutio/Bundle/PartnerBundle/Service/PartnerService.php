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

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Intl\Countries;
use Vivutio\Bundle\PartnerBundle\Entity\Partner;
use Vivutio\Bundle\PartnerBundle\Enum\PartnerKindEnum;
use Vivutio\Bundle\PartnerBundle\Enum\PartnerStatusEnum;
use Vivutio\Bundle\PartnerBundle\Exception\InvalidPartnerException;
use Vivutio\Bundle\PartnerBundle\Repository\PartnerRepository;

/**
 * Partners added, configured, archived and reactivated: named once, of a kind,
 * in a country ISO 3166 knows, with an address a request can leave for, a
 * discount from 0 to 100% and up to a year to pay.
 */
final readonly class PartnerService
{
    public const int CREDIT_DAYS_MAX = 365;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private PartnerRepository $partners,
    ) {
    }

    /**
     * @throws InvalidPartnerException
     */
    public function create(string $name, string $kind, string $country, string $email): Partner
    {
        $partner = new Partner($this->name($name, null), self::kind($kind), self::country($country), self::email($email));
        $this->entityManager->persist($partner);
        $this->entityManager->flush();

        return $partner;
    }

    /**
     * Everything a partner holds, saved together or not at all.
     *
     * @param array<string, string> $typed the form's fields: name, kind, country, email, contact, phone, discount, credit_days, notes
     *
     * @throws InvalidPartnerException
     */
    public function configure(Partner $partner, array $typed): void
    {
        $name = $this->name($typed['name'] ?? '', $partner);
        $kind = self::kind($typed['kind'] ?? '');
        $country = self::country($typed['country'] ?? '');
        $email = self::email($typed['email'] ?? '');
        $contact = self::text($typed['contact'] ?? '', 'contact', Partner::CONTACT_MAX_LENGTH);
        $phone = self::text($typed['phone'] ?? '', 'phone', Partner::PHONE_MAX_LENGTH);
        $discount = trim($typed['discount'] ?? '');
        if ('' === $discount) {
            $discount = '0';
        }
        if (1 !== preg_match('{^\d{1,3}(\.\d{1,2})?$}D', $discount) || (float) $discount > 100) {
            throw new InvalidPartnerException('discount', 'A discount is a share from 0 to 100, to the cent: 15 or 12.5.');
        }
        $days = trim($typed['credit_days'] ?? '');
        if ('' === $days) {
            $days = '0';
        }
        if (1 !== preg_match('{^\d{1,3}$}D', $days) || (int) $days > self::CREDIT_DAYS_MAX) {
            throw new InvalidPartnerException('credit_days', \sprintf('Days to pay are a whole number from 0, on booking, to %d.', self::CREDIT_DAYS_MAX));
        }
        $notes = self::text($typed['notes'] ?? '', 'notes', Partner::NOTES_MAX_LENGTH);

        $partner->setName($name)
            ->setKind($kind)
            ->setCountry($country)
            ->setEmail($email)
            ->setContact($contact)
            ->setPhone($phone)
            ->setDiscount(number_format((float) $discount, 2, '.', ''))
            ->setCreditDays((int) $days)
            ->setNotes($notes);
        $this->entityManager->flush();
    }

    public function archive(Partner $partner): void
    {
        $partner->setStatus(PartnerStatusEnum::Archived);
        $this->entityManager->flush();
    }

    public function reactivate(Partner $partner): void
    {
        $partner->setStatus(PartnerStatusEnum::Active);
        $this->entityManager->flush();
    }

    /**
     * @throws InvalidPartnerException
     */
    private function name(string $name, ?Partner $self): string
    {
        $name = trim($name);
        if ('' === $name) {
            throw new InvalidPartnerException('name', 'A partner is known by its name: it cannot be empty.');
        }
        if (mb_strlen($name) > Partner::NAME_MAX_LENGTH) {
            throw new InvalidPartnerException('name', \sprintf('A name can be at most %d characters.', Partner::NAME_MAX_LENGTH));
        }
        foreach ($this->partners->findAll() as $other) {
            if ($other !== $self && mb_strtolower($other->getName()) === mb_strtolower($name)) {
                throw new InvalidPartnerException('name', \sprintf('%s is a partner already.', $other->getName()));
            }
        }

        return $name;
    }

    /**
     * @throws InvalidPartnerException
     */
    private static function kind(string $kind): PartnerKindEnum
    {
        return PartnerKindEnum::tryFrom($kind) ?? throw new InvalidPartnerException('kind', 'Choose what the partner is to you.');
    }

    /**
     * @throws InvalidPartnerException
     */
    private static function country(string $country): string
    {
        $country = strtoupper(trim($country));
        if (!Countries::exists($country)) {
            throw new InvalidPartnerException('country', 'Choose its country.');
        }

        return $country;
    }

    /**
     * @throws InvalidPartnerException
     */
    private static function email(string $email): string
    {
        $email = trim($email);
        if (false === filter_var($email, \FILTER_VALIDATE_EMAIL) || mb_strlen($email) > Partner::EMAIL_MAX_LENGTH) {
            throw new InvalidPartnerException('email', 'Requests to a partner leave by email: give the address they read, name@example.com.');
        }

        return $email;
    }

    /**
     * @throws InvalidPartnerException
     */
    private static function text(string $text, string $field, int $max): string
    {
        $text = trim($text);
        if (mb_strlen($text) > $max) {
            throw new InvalidPartnerException($field, \sprintf('This can be at most %d characters.', $max));
        }

        return $text;
    }
}
