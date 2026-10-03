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

    /** Nothing contributes a card yet, and the page says so rather than drawing an empty grid. */
    public function testWithNothingInstalledBothSayThereIsNothingYet(): void
    {
        self::assertCount(1, $this->home($this->person('owner', TierEnum::SuperAdmin))->filter('[data-nothing-yet]'));
        self::assertCount(1, $this->home($this->person('clerk', TierEnum::Staff))->filter('[data-nothing-yet]'));
    }

    /**
     * @param list<string>|null $grants a position's pairs, or null for no position
     */
    private function person(string $name, TierEnum $tier, ?array $grants = null): User
    {
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
