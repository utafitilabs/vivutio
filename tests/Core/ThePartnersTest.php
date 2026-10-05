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
use Vivutio\Bundle\PartnerBundle\Entity\Partner;
use Vivutio\Contracts\Partner\PartnerDirectoryInterface;

/**
 * The organizations an organization trades with, entered by hand (ruled 1
 * October): what each is to it, who to write to, the terms it trades on, and
 * the channel a request reaches it by. They never sign in. Until the hub is
 * switched on every partner is reached by the manual channel: a request leaves
 * as an email and its status is recorded by hand.
 */
final class ThePartnersTest extends MigrationsTestCase
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

    public function testAPartnerIsAddedAndShowsWhatItIsAndItsTerms(): void
    {
        $this->signedInAs($this->person('Baraka', TierEnum::Admin));

        $page = $this->browser->request('GET', '/partners');
        self::assertSame('/partners', $this->browser->request('GET', '/')->filter('nav.menu')->selectLink('Partners')->attr('href'));
        $page = $this->browser->request('GET', '/partners');
        $this->browser->submit($page->selectButton('Add the partner')->form(['name' => 'Savanna Trails Safaris', 'kind' => 'tour_operator', 'country' => 'KE', 'email' => 'reservations@savanna-trails.example']));

        $partner = $this->partner('Savanna Trails Safaris');
        self::assertResponseRedirects('/partners/'.$partner->getUuid().'/configure');
        $page = $this->browser->followRedirect();
        $this->browser->submit($page->selectButton('Save partner')->form([
            'contact' => 'Wanjiru Otieno',
            'phone' => '+254 700 000 000',
            'discount' => '15',
            'credit_days' => '30',
            'notes' => 'Books the northern circuit in the dry season.',
        ]));
        self::assertResponseRedirects('/partners/'.$partner->getUuid());

        $page = $this->browser->followRedirect();
        self::assertSame('Savanna Trails Safaris', trim($page->filter('h1')->text()));
        self::assertSame('Tour operator · Kenya', trim($page->filter('.title .about')->text()));
        self::assertSame(['15% off', '30 days', 'Manual'], $page->filter('.band .v')->each(static fn (Crawler $v): string => trim((string) preg_replace('/\s+/', ' ', $v->text()))));
        self::assertStringContainsString('reservations@savanna-trails.example', $page->filter('[data-contact]')->text());

        $page = $this->browser->request('GET', '/partners');
        self::assertSame(['Savanna Trails Safaris'], $page->filter('tr[data-partner]')->each(static fn (Crawler $row): string => (string) $row->attr('data-partner')));
    }

    public function testWhatAPartnerCannotBeIsRefusedBesideItsField(): void
    {
        $this->signedInAs($this->person('Baraka', TierEnum::Admin));
        $this->add('Savanna Trails Safaris');

        foreach ([
            ['name', ['name' => 'savanna trails safaris']],
            ['email', ['name' => 'Kilele Tours', 'email' => 'not an address']],
            ['country', ['name' => 'Kilele Tours', 'country' => 'XX']],
        ] as [$field, $changed]) {
            $page = $this->add('Kilele Tours', $changed, 422);
            self::assertSame($field, $page->filter('.field.wrong')->filter('input, select')->attr('name'), $field);
        }

        $partner = $this->partner('Savanna Trails Safaris');
        foreach ([['discount', '101'], ['discount', 'some'], ['credit_days', '-1'], ['credit_days', '400']] as [$field, $value]) {
            $page = $this->browser->request('GET', '/partners/'.$partner->getUuid().'/configure');
            $form = $page->selectButton('Save partner')->form();
            $form->disableValidation();
            $page = $this->browser->submit($form->setValues([$field => $value]));
            self::assertResponseStatusCodeSame(422);
            self::assertSame($field, $page->filter('.field.wrong')->filter('input, select, textarea')->attr('name'), $field.' '.$value);
        }
    }

    /** An archived partner stays on the register, under Archived, and is offered for nothing new. */
    public function testAPartnerIsArchivedAndReactivated(): void
    {
        $this->signedInAs($this->person('Baraka', TierEnum::Admin));
        $this->add('Savanna Trails Safaris');
        $this->add('Kilele Tours', ['kind' => 'travel_agent']);
        $partner = $this->partner('Kilele Tours');

        $page = $this->browser->request('GET', '/partners/'.$partner->getUuid().'/configure');
        $this->browser->submit($page->selectButton('Archive')->form());
        self::assertResponseRedirects('/partners/'.$partner->getUuid());

        self::assertSame(['Savanna Trails Safaris'], array_map(static fn ($partner): string => $partner->getName(), $this->directory()->active()));
        $page = $this->browser->request('GET', '/partners?status=archived');
        self::assertSame(['Kilele Tours'], $page->filter('tr[data-partner]')->each(static fn (Crawler $row): string => (string) $row->attr('data-partner')));
        self::assertSame(['Active 1', 'Archived 1'], $page->filter('[data-facet="status"] a[data-chip]')->each(static fn (Crawler $chip): string => trim((string) preg_replace('/\s+/', ' ', $chip->text()))));

        $page = $this->browser->request('GET', '/partners/'.$partner->getUuid().'/configure');
        $this->browser->submit($page->selectButton('Reactivate')->form());
        self::assertCount(2, $this->directory()->active());
    }

    /** What a module reads: a partner's name, what it is, its terms, and whether it is active. */
    public function testModulesReadPartnersThroughTheContract(): void
    {
        $this->signedInAs($this->person('Baraka', TierEnum::Admin));
        $this->add('Savanna Trails Safaris');
        $partner = $this->partner('Savanna Trails Safaris');
        $page = $this->browser->request('GET', '/partners/'.$partner->getUuid().'/configure');
        $this->browser->submit($page->selectButton('Save partner')->form(['discount' => '12.5', 'credit_days' => '14']));

        $found = $this->directory()->find((string) $partner->getUuid());
        self::assertNotNull($found);
        self::assertSame('Savanna Trails Safaris', $found->getName());
        self::assertSame('tour_operator', $found->getPartnerKind());
        self::assertSame('12.50', $found->getDiscount());
        self::assertSame(14, $found->getCreditDays());
        self::assertTrue($found->isActive());
        self::assertNull($this->directory()->find('0199a6f0-0000-7000-8000-000000000000'));
    }

    public function testPartnersAreReadWithTheirPairAndManagedWithTheOther(): void
    {
        $this->signedInAs($this->person('Baraka', TierEnum::Admin));
        $this->add('Savanna Trails Safaris');
        $partner = $this->partner('Savanna Trails Safaris');

        $this->signedInAs($this->person('Elia', TierEnum::Staff, ['partners.read']));
        $page = $this->browser->request('GET', '/partners/'.$partner->getUuid());
        self::assertResponseIsSuccessful();
        self::assertCount(0, $page->filter('form[method="post"]'));
        $this->browser->request('GET', '/partners/'.$partner->getUuid().'/configure');
        self::assertResponseStatusCodeSame(403);

        $this->signedInAs($this->person('Neema', TierEnum::Staff, ['partners.read', 'partners.manage']));
        $page = $this->browser->request('GET', '/partners');
        self::assertCount(1, $page->filter('form[method="post"][action="/partners"]'));
    }

    /**
     * @param array<string, string> $values
     */
    private function add(string $name, array $values = [], int $answered = 302): Crawler
    {
        $page = $this->browser->request('GET', '/partners');
        $form = $page->selectButton('Add the partner')->form();
        $form->disableValidation();
        $page = $this->browser->submit($form->setValues([...['name' => $name, 'kind' => 'tour_operator', 'country' => 'KE', 'email' => 'reservations@'.strtolower(str_replace(' ', '-', $name)).'.example'], ...$values]));
        self::assertResponseStatusCodeSame($answered);

        return $page;
    }

    private function partner(string $name): Partner
    {
        $this->em()->clear();
        $partner = $this->em()->getRepository(Partner::class)->findOneBy(['name' => $name]);
        self::assertInstanceOf(Partner::class, $partner, $name);

        return $partner;
    }

    private function directory(): PartnerDirectoryInterface
    {
        $directory = static::getContainer()->get(PartnerDirectoryInterface::class);
        self::assertInstanceOf(PartnerDirectoryInterface::class, $directory);

        return $directory;
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
