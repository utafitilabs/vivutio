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
use Vivutio\Bundle\IdentityBundle\Access\ConcernCatalogue;
use Vivutio\Bundle\IdentityBundle\Entity\Position;
use Vivutio\Bundle\IdentityBundle\Entity\User;
use Vivutio\Bundle\IdentityBundle\Enum\TierEnum;
use Vivutio\Bundle\IdentityBundle\Service\OrganizationService;
use Vivutio\Contracts\Settings\OrganizationIdentitySourceInterface;
use Vivutio\Core\Tests\Application\Kernel;

/**
 * Settings › Organization, as designed in vivutio-designs/settings/ after
 * uhifadhi's ruling: the identity read as plain values, changed on its
 * Configure page, and only by Super Admins and Admins.
 */
final class TheOrganizationSettingsTest extends MigrationsTestCase
{
    private const string READ = '/settings/organization';

    private const string CONFIGURE = '/settings/configure/organization';

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

    public function testSuperAdminsAndAdminsFindSettingsInTheMenu(): void
    {
        foreach ([TierEnum::SuperAdmin, TierEnum::Admin] as $tier) {
            $this->signedInAs($tier);
            $page = $this->browser->request('GET', '/');

            self::assertSame(self::READ, $page->filter('nav.menu')->selectLink('Settings')->attr('href'), $tier->label());
        }
    }

    /** Even Staff whose position grants everything a position can hold: settings are the tiers' alone. */
    public function testStaffNeitherSeeNorOpenSettings(): void
    {
        $this->signedInAs(TierEnum::Staff, everyPairAPositionCanHold: true);

        $page = $this->browser->request('GET', '/');
        self::assertCount(0, $page->filter('nav.menu')->selectLink('Settings'));

        foreach (['GET' => self::READ, 'POST' => self::CONFIGURE] as $method => $address) {
            $this->browser->request($method, $address, ['name' => 'Taken over']);
            self::assertResponseStatusCodeSame(403);
        }
        $this->browser->request('GET', self::CONFIGURE);
        self::assertResponseStatusCodeSame(403);

        self::assertNull($this->source()->identity(), 'nothing was written');
    }

    /** Nothing invents a name: before it is recorded, every value says it is not set. */
    public function testBeforeTheOrganizationIsRecordedEveryValueSaysNotSet(): void
    {
        $this->signedInAs(TierEnum::Admin);

        $page = $this->browser->request('GET', self::READ);

        self::assertResponseIsSuccessful();
        self::assertSame(['Name', 'Short name', 'Time zone', 'Country'], $this->facts($page, 0));
        self::assertSame(['Not set', 'Not set', 'Not set', 'Not set'], $this->facts($page, 1));
        self::assertSame(self::CONFIGURE, $page->selectLink('Configure')->attr('href'));
        self::assertSame('Settings', trim($page->filter('nav.menu a.here')->text()));
    }

    public function testTheRecordedValuesAreReadWithWhatEachIsFor(): void
    {
        $this->organizations()->record('Vivutio Camps', 'VC', 'Africa/Dar_es_Salaam', null);
        $this->signedInAs(TierEnum::Admin);

        $page = $this->browser->request('GET', self::READ);

        self::assertSame(['Vivutio Camps', 'VC', 'Africa/Dar_es_Salaam UTC+3', 'Not set'], $this->facts($page, 1));
        self::assertSame([
            'heads the menu and the dashboard',
            'drawn where the full name will not fit',
            'the organization’s own clock, for whatever is counted by the day',
            'where the organization works',
        ], $this->facts($page, 2));
    }

    public function testTheConfigurePageHoldsEveryValueAsAField(): void
    {
        $this->organizations()->record('Vivutio Camps', 'VC', 'Africa/Dar_es_Salaam', null);
        $this->signedInAs(TierEnum::SuperAdmin);

        $page = $this->browser->request('GET', self::CONFIGURE);

        self::assertResponseIsSuccessful();
        $values = $page->selectButton('Save identity')->form()->getValues();
        self::assertSame('Vivutio Camps', $values['name']);
        self::assertSame('VC', $values['short_name']);
        self::assertSame('Africa/Dar_es_Salaam', $values['time_zone']);
        self::assertSame('', $values['country']);
        self::assertCount(1, $page->filter('select[name="time_zone"] optgroup[label="Africa"] option[value="Africa/Nairobi"]'), 'every zone the server knows, grouped by region');
        self::assertCount(1, $page->filter('footer.panel-foot button[type="reset"]'), 'Discard puts the form back');
        self::assertSame(self::READ, $page->selectLink('Back')->attr('href'));
    }

    public function testSavingRecordsTheIdentityAndSaysSo(): void
    {
        $this->signedInAs(TierEnum::Admin);

        $page = $this->browser->request('GET', self::CONFIGURE);
        $this->browser->submit($page->selectButton('Save identity')->form([
            'name' => 'Vivutio Camps',
            'short_name' => 'VC',
            'time_zone' => 'Africa/Nairobi',
            'country' => 'Kenya',
        ]));

        self::assertResponseRedirects(self::READ);
        $page = $this->browser->followRedirect();
        self::assertSelectorTextContains('.notice[role="status"]', 'The organization is saved.');
        self::assertSame('Vivutio Camps', trim($page->filter('nav.menu h2')->first()->text()));
        self::assertSame('Kenya', $this->source()->identity()?->country);

        $page = $this->browser->request('GET', self::READ);
        self::assertCount(0, $page->filter('.notice'), 'the notice shows once');
    }

    public function testAValueTheScreensCouldNotDrawIsRefusedBesideItsFieldAndNothingIsWritten(): void
    {
        $this->signedInAs(TierEnum::Admin);

        $page = $this->browser->request('GET', self::CONFIGURE);
        $page = $this->browser->submit($page->selectButton('Save identity')->form([
            'name' => '   ',
            'short_name' => 'VC',
        ]));

        self::assertResponseStatusCodeSame(422);
        self::assertSame('An organization is known by its name: it cannot be empty.', trim($page->filter('.field.wrong .hint')->text()));
        self::assertSame('name', $page->filter('.field.wrong input')->attr('name'));
        self::assertSame('VC', $page->selectButton('Save identity')->form()->getValues()['short_name'], 'what was typed is kept');
        self::assertNull($this->source()->identity());
    }

    public function testAFormWithoutItsTokenChangesNothing(): void
    {
        $this->signedInAs(TierEnum::SuperAdmin);

        $this->browser->request('POST', self::CONFIGURE, ['name' => 'Vivutio Camps']);

        self::assertResponseStatusCodeSame(422);
        self::assertNull($this->source()->identity());
    }

    private function signedInAs(TierEnum $tier, bool $everyPairAPositionCanHold = false): void
    {
        $position = null;
        if ($everyPairAPositionCanHold) {
            $catalogue = static::getContainer()->get(Kernel::CATALOGUE);
            self::assertInstanceOf(ConcernCatalogue::class, $catalogue);
            $position = (new Position())->setName('Everything')->setGrants($catalogue->positionPairs());
        }

        $user = (new User())
            ->setEmail(strtolower($tier->value).'.'.bin2hex(random_bytes(3)).'@vivutio-camps.example')
            ->setFirstName('Neema')
            ->setLastName('Mollel')
            ->setTier($tier)
            ->setPosition($position)
            ->setPassword('a hash, never a password');

        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        if (null !== $position) {
            $em->persist($position);
        }
        $em->persist($user);
        $em->flush();

        $this->browser->restart();
        $this->browser->loginUser($user);
    }

    /**
     * @return list<string> one column of the identity table
     */
    private function facts(Crawler $page, int $column): array
    {
        return $page->filter('[data-identity] tr')->each(static fn (Crawler $row): string => trim((string) preg_replace('/\s+/', ' ', $row->filter('td')->eq($column)->text())));
    }

    private function organizations(): OrganizationService
    {
        $service = static::getContainer()->get(Kernel::ORGANIZATIONS);
        self::assertInstanceOf(OrganizationService::class, $service);

        return $service;
    }

    private function source(): OrganizationIdentitySourceInterface
    {
        $source = static::getContainer()->get('test_public.organization_identity');
        self::assertInstanceOf(OrganizationIdentitySourceInterface::class, $source);

        return $source;
    }
}
