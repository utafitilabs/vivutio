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
use Vivutio\Bundle\IdentityBundle\Entity\Department;
use Vivutio\Bundle\IdentityBundle\Entity\Position;
use Vivutio\Bundle\IdentityBundle\Entity\User;
use Vivutio\Bundle\IdentityBundle\Enum\TierEnum;

/**
 * Where everybody lands, as uhifadhi rules it: `/` is the organization's
 * dashboard for whoever holds `dashboard.read`, and their own for everybody
 * else. The grant decides which page, never whether there is one.
 */
final class TheDashboardTest extends MigrationsTestCase
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

    public function testTheTiersLandOnTheOrganizationsDashboard(): void
    {
        foreach ([TierEnum::SuperAdmin, TierEnum::Admin] as $tier) {
            $page = $this->home($this->person($tier->value, $tier));

            self::assertSame('organization', $page->filter('[data-dashboard]')->attr('data-dashboard'), $tier->label());
        }
    }

    public function testAPositionThatGrantsItLandsThereToo(): void
    {
        $page = $this->home($this->person('manager', TierEnum::Staff, ['dashboard.read']));

        self::assertSame('organization', $page->filter('[data-dashboard]')->attr('data-dashboard'));
    }

    public function testEverybodyElseLandsOnTheirOwn(): void
    {
        $page = $this->home($this->person('clerk', TierEnum::Staff, ['directory.read']));

        self::assertSame('mine', $page->filter('[data-dashboard]')->attr('data-dashboard'));
        self::assertCount(0, $page->filter('a[href="/me"]'), 'nobody is offered an organization dashboard they may not read');
    }

    /** A holder reaches their own at its own address, from a link on the organization's. */
    public function testWhoeverSeesTheOrganizationsAlsoReachesTheirOwn(): void
    {
        $holder = $this->person('owner', TierEnum::SuperAdmin);

        self::assertCount(1, $this->home($holder)->filter('a[href="/me"]'));

        $this->browser->request('GET', '/me');
        self::assertResponseIsSuccessful();
        self::assertSame('mine', $this->browser->getCrawler()->filter('[data-dashboard]')->attr('data-dashboard'));
    }

    /** A dashboard of one's own is somebody's: it names who it belongs to. */
    public function testOnesOwnDashboardNamesItsOwner(): void
    {
        $this->home($this->person('clerk', TierEnum::Staff));

        self::assertSelectorTextContains('[data-dashboard="mine"]', 'Clerk Kimaro');
    }

    /** Nothing the person may open contributes, and the page says so rather than drawing an empty grid. */
    public function testWithNothingToShowBothSayThereIsNothingYet(): void
    {
        self::assertCount(1, $this->home($this->person('manager', TierEnum::Staff, ['dashboard.read']))->filter('[data-nothing-yet]'));
        self::assertCount(1, $this->home($this->person('clerk', TierEnum::Staff))->filter('[data-nothing-yet]'));
    }

    /** The band carries every package's figures, in their order, for whoever may read them. */
    public function testThePackagesFiguresStandInTheBand(): void
    {
        $page = $this->home($this->person('owner', TierEnum::SuperAdmin));

        self::assertSame(['Written this week 3 in 2 notebooks', 'Notebooks 2'], $page->filter('.band .f')->each(static fn (Crawler $f): string => self::words($f)));
        self::assertCount(0, $page->filter('[data-nothing-yet]'));
    }

    /** A card is bounded: its latest rows, how many in all, one door to the package's page. */
    public function testACardShowsItsLatestRowsAndOneDoor(): void
    {
        $card = $this->card($this->home($this->person('owner', TierEnum::SuperAdmin)), 'Latest notes');

        self::assertSame('Notes · the newest first', self::words($card->filter('.panel-head p')));
        self::assertSame(['NT-0005 Field notebook Mon 12 Oct New', 'NT-0004 Field notebook Sun 11 Oct'], $card->filter('tbody tr')->each(static fn (Crawler $row): string => self::words($row)));
        self::assertSame('chip warn', $card->filter('.chip')->attr('class'));
        self::assertSame('2 of 5', self::words($card->filter('.panel-foot .state')));
        self::assertSame('/notes', $card->filter('.panel-foot a.more')->attr('href'));
        self::assertSame('All notes', self::words($card->filter('.panel-foot a.more')));
    }

    public function testACardWithNothingInItSaysSo(): void
    {
        $card = $this->card($this->home($this->person('owner', TierEnum::SuperAdmin)), 'Unread');

        self::assertCount(0, $card->filter('tbody tr'));
        self::assertSame('Nothing is unread', self::words($card->filter('[data-card-empty]')));
    }

    /** Cards are dealt into the two columns in turn, by their order: the first left, the second right. */
    public function testCardsAreDealtIntoTwoColumnsByTheirOrder(): void
    {
        $page = $this->home($this->person('owner', TierEnum::SuperAdmin));
        $column = static fn (int $n): array => $page->filter('[data-cards] > .stack')->eq($n)->filter('.panel-head h2')->each(static fn (Crawler $h): string => self::words($h));

        self::assertSame(['Latest notes', 'Unread'], $column(0));
        self::assertSame(['Pinned'], $column(1));
    }

    /** What one may not open is not offered on the dashboard either. */
    public function testOnlyWhatThePersonMayOpenIsShown(): void
    {
        $page = $this->home($this->person('reader', TierEnum::Staff, ['dashboard.read', 'notes.read'], ['notes.read']));

        self::assertSame(['Written this week 3 in 2 notebooks'], $page->filter('.band .f')->each(static fn (Crawler $f): string => self::words($f)));
        self::assertCount(3, $page->filter('[data-cards] .panel'));
    }

    private function card(Crawler $page, string $title): Crawler
    {
        $card = $page->filter('[data-cards] .panel')->reduce(static fn (Crawler $panel): bool => self::words($panel->filter('.panel-head h2')) === $title);
        self::assertCount(1, $card, $title);

        return $card;
    }

    /** The words of a node, each element's own apart, as a reader sees them. */
    private static function words(Crawler $node): string
    {
        $words = [];
        foreach ($node as $element) {
            foreach ((new \DOMXPath($element->ownerDocument ?? new \DOMDocument()))->query('.//text()', $element) ?: [] as $text) {
                if ($text instanceof \DOMText) {
                    $words[] = trim((string) preg_replace('/\s+/', ' ', $text->textContent));
                }
            }
        }

        return implode(' ', array_filter($words, static fn (string $word): bool => '' !== $word));
    }

    /**
     * @param list<string>|null $grants a position's pairs, or null for no position
     * @param list<string>      $allows what the person's department allows of a module
     */
    private function person(string $name, TierEnum $tier, ?array $grants = null, array $allows = []): User
    {
        $department = null;
        if ([] !== $allows) {
            $department = (new Department())->setName($name.'\'s department')->setAllows($allows);
            $this->em->persist($department);
        }
        $position = null;
        if (null !== $grants) {
            $position = (new Position())->setName('Seat of '.$name)->setGrants($grants);
            $this->em->persist($position);
        }

        $user = (new User())
            ->setEmail($name.'@vivutio-camps.example')
            ->setFirstName(ucfirst($name))
            ->setLastName('Kimaro')
            ->setTier($tier)
            ->setPosition($position)
            ->setDepartment($department)
            ->setPassword('a hash, never a password');
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function home(User $user): Crawler
    {
        $this->browser->restart();
        $this->browser->loginUser($user);
        $page = $this->browser->request('GET', '/');
        self::assertResponseIsSuccessful();

        return $page;
    }
}
