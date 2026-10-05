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
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Crawler;
use Vivutio\Bundle\IdentityBundle\Entity\Position;
use Vivutio\Bundle\IdentityBundle\Entity\User;
use Vivutio\Bundle\IdentityBundle\Enum\TierEnum;
use Vivutio\Bundle\IdentityBundle\Service\OrganizationService;
use Vivutio\Core\Tests\Application\Kernel;

/**
 * The frame every signed-in page wears, as designed in
 * vivutio-designs/shell/dashboard.html: one bar across the top, the menu under
 * it listing only what the person may open.
 */
final class TheFrameTest extends MigrationsTestCase
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

    /**
     * @return iterable<string, array{string}>
     */
    public static function signedInPages(): iterable
    {
        yield 'the dashboard' => ['/'];
        yield 'one\'s own dashboard' => ['/me'];
        yield 'the team' => ['/team'];
    }

    #[DataProvider('signedInPages')]
    public function testEverySignedInPageWearsTheBar(string $address): void
    {
        $page = $this->open($address, $this->person('Neema', TierEnum::SuperAdmin));

        self::assertSame('/', $page->filter('header.bar a.brand')->attr('href'));
        self::assertCount(1, $page->filter('header.bar button.fold'));
        self::assertCount(1, $page->filter('header.bar button[onclick="toggleTheme()"]'));
        self::assertSame('Neema Kimaro', trim($page->filter('header.bar .bar-me b')->text()));
    }

    /**
     * Kept so they are not forgotten, and drawn as coming: a field nobody can
     * type in and a bell nobody can press, each saying so.
     */
    public function testSearchAndNotificationsAreDrawnAsComing(): void
    {
        $page = $this->open('/', $this->person('Neema', TierEnum::SuperAdmin));

        self::assertCount(1, $page->filter('header.bar .search input[disabled]'));
        self::assertSame('Search is coming', $page->filter('header.bar .search')->attr('title'));
        self::assertSame('Notifications are coming', $page->filter('header.bar button.bar-icon[disabled]')->attr('title'));
    }

    /** Under the name, the tiers read their tier; Staff, who see no tier, read their position. */
    public function testTheLineUnderTheNameFollowsTheTierRule(): void
    {
        self::assertSame('Super Admin', $this->lineFor($this->person('Neema', TierEnum::SuperAdmin)));
        self::assertSame('Admin', $this->lineFor($this->person('Baraka', TierEnum::Admin)));
        self::assertSame('Housekeeper', $this->lineFor($this->person('Amani', TierEnum::Staff, ['directory.read'], 'Housekeeper')));
        self::assertSame('No position', $this->lineFor($this->person('Elia', TierEnum::Staff)));
    }

    /** The name in the bar opens signing out, which ends the session. */
    public function testTheNameInTheBarOpensSignOut(): void
    {
        $page = $this->open('/', $this->person('Neema', TierEnum::SuperAdmin));

        self::assertSame('/logout', $page->filter('header.bar details.me a')->attr('href'));
    }

    public function testTheMenuListsOnlyWhatThePersonMayOpen(): void
    {
        $withDirectory = $this->open('/', $this->person('Amani', TierEnum::Staff, ['directory.read'], 'Housekeeper'));
        self::assertSame(['Dashboard', 'Team'], $this->menu($withDirectory));

        $without = $this->open('/', $this->person('Elia', TierEnum::Staff));
        self::assertSame(['Dashboard'], $this->menu($without));
    }

    public function testTheMenuMarksThePageOneIsOn(): void
    {
        $page = $this->open('/team', $this->person('Neema', TierEnum::SuperAdmin));

        self::assertSame('Team', trim($page->filter('nav.menu a.here')->text()));
    }

    /** The organization's name heads the menu once it is recorded; there is no record yet, so there is no heading. */
    public function testWithoutAnOrganizationRecordTheMenuHasNoNameOverIt(): void
    {
        $page = $this->open('/', $this->person('Neema', TierEnum::SuperAdmin));

        self::assertSame('Organization', trim($page->filter('nav.menu h2')->first()->text()));
    }

    public function testOnceRecordedTheOrganizationsNameHeadsTheMenuAndTheDashboard(): void
    {
        $organizations = static::getContainer()->get(Kernel::ORGANIZATIONS);
        self::assertInstanceOf(OrganizationService::class, $organizations);
        $organizations->record('Vivutio Camps', 'VC', null, null);

        $page = $this->open('/', $this->person('Neema', TierEnum::SuperAdmin));

        self::assertSame('Vivutio Camps', trim($page->filter('nav.menu h2')->first()->text()));
        self::assertSame('Vivutio Camps', trim($page->filter('.page-head .about')->text()));
    }

    /**
     * A module puts its pages in the menu through the shell's seam, never by
     * the core naming it: under the organization's name, after the dashboard,
     * and only for whoever may open them.
     */
    public function testAModulesPagesJoinTheMenuForWhoeverMayOpenThem(): void
    {
        $tier = $this->open('/', $this->person('Neema', TierEnum::SuperAdmin));
        self::assertSame(['Dashboard', 'Notes', 'Team', 'Departments', 'Offices', 'Partners', 'Destinations', 'Positions', 'Settings'], $this->menu($tier));
        self::assertSame('/notes', $tier->filter('nav.menu')->selectLink('Notes')->attr('href'));

        $staff = $this->open('/', $this->person('Amani', TierEnum::Staff, ['notes.read'], 'Housekeeper'));
        self::assertSame(['Dashboard'], $this->menu($staff), 'a module\'s pair needs a department that allows it');
    }

    /**
     * @param list<string>|null $grants
     */
    private function person(string $name, TierEnum $tier, ?array $grants = null, string $seat = 'Seat'): User
    {
        $position = null;
        if (null !== $grants) {
            $position = (new Position())->setName($seat)->setGrants($grants);
            $this->em->persist($position);
        }

        $user = (new User())
            ->setEmail(strtolower($name).'@vivutio-camps.example')
            ->setFirstName($name)
            ->setLastName('Kimaro')
            ->setTier($tier)
            ->setPosition($position)
            ->setPassword('a hash, never a password');
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function open(string $address, User $user): Crawler
    {
        $this->browser->restart();
        $this->browser->loginUser($user);
        $page = $this->browser->request('GET', $address);
        self::assertResponseIsSuccessful();

        return $page;
    }

    private function lineFor(User $user): string
    {
        return trim($this->open('/', $user)->filter('header.bar .bar-me small')->text());
    }

    /**
     * @return list<string>
     */
    private function menu(Crawler $page): array
    {
        return $page->filter('nav.menu a')->each(static fn (Crawler $link): string => trim($link->text()));
    }
}
