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

/**
 * The team list, ported from uhifadhi's table: who may open it, what each
 * viewer is shown of each person, and the search, filters, counts and pager,
 * driven over HTTP.
 */
final class TheTeamListTest extends MigrationsTestCase
{
    private KernelBrowser $browser;

    private EntityManagerInterface $em;

    protected function start(): void
    {
        $this->browser = static::createClient();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate();

        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $this->em = $em;
    }

    public function testItIsRefusedToAPositionThatDoesNotGrantTheDirectory(): void
    {
        $this->signedInAs($this->person('Neema', 'Mollel', TierEnum::Staff, $this->position('Clerk', [])));

        $this->browser->request('GET', '/team');

        self::assertResponseStatusCodeSame(403);
    }

    /** The team is read on screen; nothing carries the list out as a file. */
    public function testTheListHasNoExport(): void
    {
        $this->theCamp();
        $this->signedInAs($this->person('Zawadi', 'Lema', TierEnum::SuperAdmin));

        self::assertCount(0, $this->list()->filter('a[data-export]'));
        $this->browser->request('GET', '/team/export');
        self::assertResponseStatusCodeSame(404);
    }

    public function testItListsEverybodyToWhoeverMayReadTheDirectory(): void
    {
        $this->theCamp();
        $viewer = $this->person('Neema', 'Mollel', TierEnum::Staff, $this->position('Clerk', ['directory.read']));
        $this->signedInAs($viewer);

        $page = $this->list();

        self::assertSame(['Amani Lyimo', 'Baraka Kimaro', 'Elia Massawe', 'Grace Mushi', 'Jabiri Swai', 'Neema Mollel'], $this->names($page), 'by name');
        self::assertSelectorTextContains('[data-shown]', 'showing 6 of 6');
    }

    /** Without personal details a viewer sees who is on the team, and not how to reach them. */
    public function testAnAddressIsShownOnlyToWhoeverMayReadPersonalDetails(): void
    {
        $this->theCamp();

        $this->signedInAs($this->person('Neema', 'Mollel', TierEnum::Staff, $this->position('Clerk', ['directory.read'])));
        self::assertStringNotContainsString('baraka.kimaro@vivutio-camps.example', (string) $this->list()->html());

        $this->signedInAs($this->person('Halima', 'Saidi', TierEnum::Staff, $this->position('Office', ['directory.read', 'personal_details.read'])));
        self::assertStringContainsString('baraka.kimaro@vivutio-camps.example', (string) $this->list()->html());
    }

    /**
     * Searching by address is reading addresses: whoever may not read them
     * finds by name only, so a search cannot confirm that an address is here.
     */
    public function testAnAddressIsSearchedOnlyByWhoeverMayReadIt(): void
    {
        $this->theCamp();

        $this->signedInAs($this->person('Neema', 'Mollel', TierEnum::Staff, $this->position('Clerk', ['directory.read'])));
        self::assertSame([], $this->names($this->list('?q=baraka.kimaro%40')));
        self::assertSame(['Baraka Kimaro'], $this->names($this->list('?q=kima')));

        $this->signedInAs($this->person('Halima', 'Saidi', TierEnum::Staff, $this->position('Office', ['directory.read', 'personal_details.read'])));
        self::assertSame(['Baraka Kimaro'], $this->names($this->list('?q=baraka.kimaro%40')));
    }

    /**
     * In a list, tiers are a Super Admin's alone: the column, the chips and
     * their counts. An Admin's list has no tier column, so no blank cell
     * marks the Super Admins, and a tier in the address is ignored, so no
     * filter sorts them out either.
     */
    public function testOnlyASuperAdminSeesTiersInTheList(): void
    {
        $this->theCamp();

        $this->signedInAs($this->person('Halima', 'Saidi', TierEnum::Admin));
        $page = $this->list();
        self::assertCount(0, $page->filter('[data-tier]'));
        self::assertStringNotContainsString('Super Admin', (string) $page->html());
        self::assertCount(6, $this->names($this->list('?tier=super_admin')), 'a tier in the address is ignored for an Admin');

        $this->signedInAs($this->person('Zawadi', 'Lema', TierEnum::SuperAdmin));
        $page = $this->list();
        self::assertSame('Super Admin', trim($page->filter('tr[data-person="Grace Mushi"] [data-tier]')->text()));
        self::assertSame(['All 7', 'Super Admin 2', 'Admin 2', 'Staff 3'], $this->chips($page, 'tier'));
        self::assertSame(['Grace Mushi', 'Zawadi Lema'], $this->names($this->list('?tier=super_admin')));
    }

    public function testTheFiltersCountTheWholeTeamAndFilterIt(): void
    {
        $this->theCamp();
        $this->signedInAs($this->person('Zawadi', 'Lema', TierEnum::SuperAdmin));

        $page = $this->list('?q=baraka');
        self::assertSame(['Any 6', 'Driver guide 1', 'Housekeeper 2', 'No position 3'], $this->chips($page, 'position'), 'counts are over the whole team, not the search');
        self::assertSame(['Any 6', 'Active 5', 'Deactivated 1'], $this->chips($page, 'account'));

        $housekeeper = $this->em->getRepository(Position::class)->findOneBy(['name' => 'Housekeeper']);
        self::assertInstanceOf(Position::class, $housekeeper);
        self::assertSame(['Amani Lyimo', 'Jabiri Swai'], $this->names($this->list('?position='.$housekeeper->getUuid())));
        self::assertSame(['Jabiri Swai'], $this->names($this->list('?account=deactivated')));
    }

    /** The signed-in person's own row says so, as uhifadhi's does. */
    public function testOnesOwnRowIsMarked(): void
    {
        $this->theCamp();
        $this->signedInAs($this->person('Zawadi', 'Lema', TierEnum::SuperAdmin));

        $page = $this->list();

        self::assertSame(['Zawadi Lema'], $page->filter('tr[data-person]')->reduce(static fn (Crawler $row): bool => $row->filter('.you')->count() > 0)->each(static fn (Crawler $row): string => (string) $row->attr('data-person')));
    }

    /** "Everything, by tier" names a tier, so it is said only to whoever sees tiers. */
    public function testEverythingByTierIsSaidOnlyToWhoeverSeesTiers(): void
    {
        $this->theCamp();

        $this->signedInAs($this->person('Zawadi', 'Lema', TierEnum::SuperAdmin));
        self::assertStringContainsString('everything, by tier', $this->list()->filter('tr[data-person="Baraka Kimaro"]')->text());

        $this->signedInAs($this->person('Halima', 'Saidi', TierEnum::Admin));
        self::assertStringNotContainsString('by tier', (string) $this->list()->html());
    }

    public function testAFilterThatMatchesNobodySaysSoAndOffersTheWholeTeam(): void
    {
        $this->theCamp();
        $this->signedInAs($this->person('Zawadi', 'Lema', TierEnum::SuperAdmin));

        $page = $this->list('?q=nobody-here');

        self::assertStringContainsString('Nobody matches “nobody-here”', $page->filter('[data-nobody]')->text());
        self::assertSame('/team', $page->filter('[data-nobody] a')->attr('href'));
    }

    public function testTwentyFivePeopleArePutOnAPage(): void
    {
        for ($i = 1; $i <= 30; ++$i) {
            $this->person('Person', \sprintf('%02d', $i), TierEnum::Staff);
        }
        $this->signedInAs($this->person('Zawadi', 'Lema', TierEnum::SuperAdmin));

        $first = $this->list();
        self::assertCount(25, $this->names($first));
        self::assertSelectorTextContains('[data-pager]', '31 people · 25 per page');
        self::assertSelectorTextContains('[data-pager]', 'page 1 of 2');

        self::assertCount(6, $this->names($this->list('?page=2')));
    }

    /** One account, and it is yours: a list of one has nothing to filter. */
    public function testTheFirstRunHasNoFiltersAndSaysWhy(): void
    {
        $this->signedInAs($this->person('Zawadi', 'Lema', TierEnum::SuperAdmin));

        $page = $this->list();

        self::assertCount(0, $page->filter('form[data-tools]'));
        self::assertCount(1, $page->filter('[data-first-run]'));
    }

    /**
     * Vivutio Riverside Camp: two Super Admins, an Admin and three Staff, one
     * of them deactivated.
     */
    private function theCamp(): void
    {
        $housekeeper = $this->position('Housekeeper', []);
        $guide = $this->position('Driver guide', []);

        $this->person('Grace', 'Mushi', TierEnum::SuperAdmin);
        $this->person('Baraka', 'Kimaro', TierEnum::Admin);
        $this->person('Amani', 'Lyimo', TierEnum::Staff, $housekeeper);
        $this->person('Jabiri', 'Swai', TierEnum::Staff, $housekeeper, active: false);
        $this->person('Elia', 'Massawe', TierEnum::Staff, $guide);
    }

    /**
     * @param list<string> $grants
     */
    private function position(string $name, array $grants): Position
    {
        $position = (new Position())->setName($name)->setGrants($grants);
        $this->em->persist($position);
        $this->em->flush();

        return $position;
    }

    private function person(string $first, string $last, TierEnum $tier, ?Position $position = null, bool $active = true): User
    {
        $user = (new User())
            ->setEmail(strtolower($first.'.'.$last).'@vivutio-camps.example')
            ->setFirstName($first)
            ->setLastName($last)
            ->setTier($tier)
            ->setPosition($position)
            ->setActive($active)
            ->setPassword('a hash, never a password');
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function signedInAs(User $user): void
    {
        $this->browser->restart();
        $this->browser->loginUser($user);
    }

    private function list(string $query = ''): Crawler
    {
        $page = $this->browser->request('GET', '/team'.$query);
        self::assertResponseIsSuccessful();

        return $page;
    }

    /**
     * @return list<string>
     */
    private function names(Crawler $page): array
    {
        return $page->filter('tr[data-person]')->each(static fn (Crawler $row): string => (string) $row->attr('data-person'));
    }

    /**
     * @return list<string>
     */
    private function chips(Crawler $page, string $facet): array
    {
        return $page->filter(\sprintf('[data-facet="%s"] [data-chip]', $facet))->each(static function (Crawler $chip): string {
            // The count is its own element, spaced from the label by the stylesheet.
            $count = trim($chip->filter('em')->text());

            return trim((string) preg_replace('/\s+/', ' ', substr(trim($chip->text()), 0, -\strlen($count)))).' '.$count;
        });
    }
}
