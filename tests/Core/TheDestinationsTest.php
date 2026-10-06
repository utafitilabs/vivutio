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

namespace Vivutio\Core\Tests\Core;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Crawler;
use Vivutio\Bundle\IdentityBundle\Entity\Position;
use Vivutio\Bundle\IdentityBundle\Entity\User;
use Vivutio\Bundle\IdentityBundle\Enum\TierEnum;
use Vivutio\Bundle\PlaceBundle\Entity\Destination;
use Vivutio\Bundle\PlaceBundle\Enum\GuestEnum;
use Vivutio\Bundle\PlaceBundle\Enum\ResidencyEnum;
use Vivutio\Bundle\PlaceBundle\Service\DestinationFeeService;
use Vivutio\Bundle\PlaceBundle\Service\DestinationService;

/**
 * Places as reference data (ruled #17): the destinations tours go to, the
 * parks and reserves of East Africa shipped under stable keys, those an
 * installation adds, and the fees charged at each, by kind, guest, residency
 * and what they are per, dated. The core ships no amounts: fees change, and
 * an installation enters them.
 */
final class TheDestinationsTest extends MigrationsTestCase
{
    private KernelBrowser $browser;

    protected function start(): void
    {
        $this->browser = static::createClient();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate();
    }

    public function testTheCoreShipsTheParksOfEastAfricaUnderKeys(): void
    {
        $serengeti = $this->destination('tz-serengeti-national-park');
        self::assertSame('Serengeti National Park', $serengeti->getName());
        self::assertSame('national_park', $serengeti->getKind()->value);
        self::assertSame('TZ', $serengeti->getCountry());
        self::assertTrue($serengeti->isShipped());
        self::assertSame('Ngorongoro Conservation Area', $this->destination('tz-ngorongoro-conservation-area')->getName());
        self::assertSame('Maasai Mara National Reserve', $this->destination('ke-maasai-mara-national-reserve')->getName());

        $this->signedInAs($this->person('Baraka', TierEnum::Admin));
        $page = $this->browser->request('GET', '/destinations');
        self::assertResponseIsSuccessful();
        self::assertSame(['Kenya', 'Rwanda', 'Tanzania', 'Uganda'], $page->filter('tr[data-country]')->each(static fn (Crawler $row): string => trim($row->text())));
        self::assertSame('/destinations', $this->browser->request('GET', '/')->filter('nav.menu')->selectLink('Destinations')->attr('href'));
    }

    /** A shipped destination is keyed as an added one is: its country and its whole name, so a park never takes a town's key. */
    public function testEveryShippedKeyIsItsCountryAndItsWholeName(): void
    {
        $this->em()->clear();
        foreach ($this->em()->getRepository(Destination::class)->findBy(['shipped' => true]) as $destination) {
            self::assertSame(DestinationService::keyOf($destination->getCountry(), $destination->getName()), $destination->getKey());
        }
        self::assertSame('tz-arusha-national-park', $this->destination('tz-arusha-national-park')->getKey());
    }

    public function testATierAddsADestinationAnywhere(): void
    {
        $this->signedInAs($this->person('Baraka', TierEnum::Admin));

        $page = $this->browser->request('GET', '/destinations');
        self::assertContains('Botswana', $page->filter('select[name="country"] option')->each(static fn (Crawler $option): string => trim($option->text())));
        $this->browser->submit($page->selectButton('Add the destination')->form(['name' => 'Lake Natron', 'kind' => 'lake', 'country' => 'TZ']));

        $natron = $this->destination('tz-lake-natron');
        self::assertFalse($natron->isShipped());
        self::assertResponseRedirects('/destinations/'.$natron->getKey());

        foreach ([['lake natron', 'TZ', 'name'], ['Okavango', 'XX', 'country']] as [$name, $country, $field]) {
            $page = $this->browser->request('GET', '/destinations');
            $form = $page->selectButton('Add the destination')->form();
            $form->disableValidation();
            $page = $this->browser->submit($form->setValues(['name' => $name, 'kind' => 'lake', 'country' => $country]));
            self::assertResponseStatusCodeSame(422);
            self::assertSame($field, $page->filter('.field.wrong')->filter('input, select')->attr('name'));
        }
    }

    /** A fee is charged by kind, guest and residency, per a person a day, a person an entry or a vehicle an entry, between two days. */
    public function testFeesAreByGuestResidencyAndDate(): void
    {
        $this->signedInAs($this->person('Baraka', TierEnum::Admin));
        foreach ([
            ['entry', 'adult', 'non_resident', 'person_day', '80', 'USD'],
            ['entry', 'child', 'non_resident', 'person_day', '20', 'USD'],
            ['entry', 'adult', 'citizen', 'person_day', '11800', 'TZS'],
        ] as [$kind, $guest, $residency, $per, $amount, $currency]) {
            $this->addFee('tz-serengeti-national-park', ['kind' => $kind, 'guest' => $guest, 'residency' => $residency, 'per' => $per, 'amount' => $amount, 'currency' => $currency, 'valid_from' => '2026-07-01', 'valid_to' => '2027-06-30']);
        }

        $page = $this->browser->request('GET', '/destinations/tz-serengeti-national-park');
        self::assertSame(['Entry · Adult · Non-resident: USD 80.00 a person a day', 'Entry · Child · Non-resident: USD 20.00 a person a day', 'Entry · Adult · Citizen: TZS 11,800.00 a person a day'], $page->filter('[data-fee]')->each(static fn (Crawler $fee): string => trim((string) preg_replace('/\s+/', ' ', $fee->filter('span')->text().': '.$fee->filter('b')->text()))));

        $fees = static::getContainer()->get(DestinationFeeService::class);
        self::assertInstanceOf(DestinationFeeService::class, $fees);
        $serengeti = $this->destination('tz-serengeti-national-park');
        self::assertSame(['80.00'], array_map(static fn ($fee): string => $fee->getAmount(), $fees->charged($serengeti, new \DateTimeImmutable('2026-08-01'), GuestEnum::Adult, ResidencyEnum::NonResident)));
        self::assertSame([], $fees->charged($serengeti, new \DateTimeImmutable('2026-06-30'), GuestEnum::Adult, ResidencyEnum::NonResident), 'not yet in force');
    }

    /** A fee for an activity at a destination, the Crater descent, is told apart from the fee for being there. */
    public function testAFeeMayBeForAnActivity(): void
    {
        $this->signedInAs($this->person('Baraka', TierEnum::Admin));
        $year = ['guest' => 'adult', 'residency' => 'non_resident', 'currency' => 'USD', 'valid_from' => '2026-07-01', 'valid_to' => '2027-06-30'];
        $this->addFee('tz-ngorongoro-conservation-area', [...$year, 'kind' => 'conservation', 'per' => 'person_day', 'amount' => '70.80', 'activity' => '']);
        $this->addFee('tz-ngorongoro-conservation-area', [...$year, 'kind' => 'service', 'per' => 'vehicle_entry', 'amount' => '295', 'activity' => ' Crater descent ']);
        $this->addFee('tz-ngorongoro-conservation-area', [...$year, 'kind' => 'service', 'per' => 'vehicle_entry', 'amount' => '50', 'activity' => 'Olduvai walk']);

        $page = $this->browser->request('GET', '/destinations/tz-ngorongoro-conservation-area');
        self::assertSame(['Conservation · Adult · Non-resident', 'Service · Adult · Non-resident · Crater descent', 'Service · Adult · Non-resident · Olduvai walk'], $page->filter('[data-fee] span')->each(static fn (Crawler $span): string => trim((string) preg_replace('/\s+/', ' ', $span->text()))));

        $fees = static::getContainer()->get(DestinationFeeService::class);
        self::assertInstanceOf(DestinationFeeService::class, $fees);
        $area = $this->destination('tz-ngorongoro-conservation-area');
        self::assertSame(['Crater descent', 'Olduvai walk'], $fees->activitiesAt($area));
        self::assertSame([null, 'Crater descent', 'Olduvai walk'], array_map(static fn ($fee): ?string => $fee->getActivity(), $fees->charged($area, new \DateTimeImmutable('2026-08-01'), GuestEnum::Adult, ResidencyEnum::NonResident)));

        $page = $this->addFee('tz-ngorongoro-conservation-area', [...$year, 'kind' => 'service', 'per' => 'vehicle_entry', 'amount' => '300', 'activity' => 'crater descent'], 422);
        self::assertSame('valid_from', $page->filter('.field.wrong')->filter('input, select')->attr('name'));
        $page = $this->addFee('tz-ngorongoro-conservation-area', [...$year, 'kind' => 'service', 'per' => 'vehicle_entry', 'amount' => '300', 'activity' => str_repeat('A walk ', 10)], 422);
        self::assertSame('activity', $page->filter('.field.wrong')->filter('input, select')->attr('name'));
    }

    public function testAFeeIsRefusedBesideItsField(): void
    {
        $this->signedInAs($this->person('Baraka', TierEnum::Admin));
        $fee = ['kind' => 'entry', 'guest' => 'adult', 'residency' => 'non_resident', 'per' => 'person_day', 'amount' => '80', 'currency' => 'USD', 'valid_from' => '2026-07-01', 'valid_to' => '2027-06-30'];
        $this->addFee('tz-serengeti-national-park', $fee);

        foreach ([
            ['amount', ['amount' => 'eighty']],
            ['currency', ['currency' => 'dollars']],
            ['valid_to', ['valid_to' => '2026-06-01']],
            ['valid_from', ['valid_from' => '2027-01-01', 'valid_to' => '2027-12-31']],
        ] as [$field, $changed]) {
            $page = $this->addFee('tz-serengeti-national-park', [...$fee, ...$changed], 422);
            self::assertSame($field, $page->filter('.field.wrong')->filter('input, select')->attr('name'), $field);
        }
    }

    public function testStaffReadDestinationsAndChangeNone(): void
    {
        $this->signedInAs($this->person('Elia', TierEnum::Staff, ['destinations.read']));

        $page = $this->browser->request('GET', '/destinations/tz-serengeti-national-park');
        self::assertResponseIsSuccessful();
        self::assertCount(0, $page->filter('form[method="post"]'));
        $this->browser->request('POST', '/destinations', ['name' => 'Taken over', 'kind' => 'lake', 'country' => 'TZ']);
        self::assertResponseStatusCodeSame(403);
    }

    /**
     * @param array<string, string> $values
     */
    private function addFee(string $key, array $values, int $answered = 302): Crawler
    {
        $page = $this->browser->request('GET', '/destinations/'.$key);
        $form = $page->selectButton('Add the fee')->form();
        $form->disableValidation();
        $page = $this->browser->submit($form->setValues($values));
        self::assertResponseStatusCodeSame($answered);

        return $page;
    }

    private function destination(string $key): Destination
    {
        $this->em()->clear();
        $destination = $this->em()->getRepository(Destination::class)->findOneBy(['key' => $key]);
        self::assertInstanceOf(Destination::class, $destination, $key);

        return $destination;
    }

    /**
     * @param list<string> $grants
     */
    private function person(string $name, TierEnum $tier, array $grants = []): User
    {
        $position = (new Position())->setName($name.'\'s seat')->setGrants($grants);
        $this->em()->persist($position);
        $user = (new User())
            ->setEmail(strtolower($name).'@vivutio-camps.example')
            ->setFirstName($name)
            ->setLastName('Kimaro')
            ->setTier($tier)
            ->setPosition($position)
            ->setPassword('a hash, never a password');
        $this->em()->persist($user);
        $this->em()->flush();

        return $user;
    }

    private function signedInAs(User $user): void
    {
        $this->browser->restart();
        $this->browser->loginUser($user);
    }

    private function em(): EntityManagerInterface
    {
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $em);

        return $em;
    }
}
