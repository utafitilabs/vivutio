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
use Vivutio\Bundle\IdentityBundle\Entity\Office;
use Vivutio\Bundle\IdentityBundle\Entity\Position;
use Vivutio\Bundle\IdentityBundle\Entity\User;
use Vivutio\Bundle\IdentityBundle\Enum\TierEnum;

/**
 * The delete as drawn (vivutio-designs positions/configure, delete, settings/
 * deletions): a Danger card on the record's Configure page, Super Admins
 * alone; a page of its own saying what goes and what stays, confirmed by
 * typing the reference; back to the register with a message; and the line in
 * Settings › Deletions.
 */
final class TheDeletePagesTest extends MigrationsTestCase
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

    public function testASuperAdminDeletesAPositionFromItsConfigurePage(): void
    {
        $guide = (new Position())->setName('Guide')->setGrants([]);
        $this->em()->persist($guide);
        $this->person('Amani', TierEnum::Staff, $guide);
        $this->signedInAs($this->person('Neema', TierEnum::SuperAdmin));

        $page = $this->browser->request('GET', '/team/positions/'.$guide->getUuid().'/configure');
        $page = $this->browser->click($page->filter('[data-danger]')->selectLink('Delete…')->link());
        self::assertSame('Delete Guide', trim($page->filter('h1')->text()));
        self::assertSame(['1 position'], $this->lines($page, 'goes'));
        self::assertSame(['1 person, who keeps their account without a seat'], $this->lines($page, 'stays'));

        $page = $this->browser->submit($page->selectButton('Delete the position')->form(['reference' => 'guide']));
        self::assertResponseStatusCodeSame(422);
        self::assertSame('Type Guide exactly, to say it is the one to delete.', trim($page->filter('.field.wrong .hint')->text()));

        $this->browser->submit($page->selectButton('Delete the position')->form(['reference' => 'Guide']));
        self::assertResponseRedirects('/team/positions');
        $page = $this->browser->followRedirect();
        self::assertSame('Position Guide is deleted.', trim($page->filter('.notice[data-deleted]')->text()));

        $page = $this->browser->request('GET', '/settings/deletions');
        self::assertSame(['Position Guide', 'Neema Kimaro · 1 position'], $page->filter('[data-deletion]')->first()->filter('span, b')->each(static fn (Crawler $part): string => trim((string) preg_replace('/^\d\d:\d\d · /', '', trim($part->text())))));
        self::assertSame('Deletions', trim($page->filter('nav.tabs [aria-current="page"]')->text()));
    }

    /** Each core record has its Danger card and delete page: people, positions, departments and offices. */
    public function testEveryCoreRecordHasItsDeletePage(): void
    {
        $office = (new Office())->setName('Head office')->setCity('Arusha')->setCountry('Tanzania');
        $this->em()->persist($office);
        $sales = (new Department())->setName('Sales');
        $this->em()->persist($sales);
        $amani = $this->person('Amani', TierEnum::Staff);
        $this->signedInAs($this->person('Neema', TierEnum::SuperAdmin));

        foreach ([
            '/team/'.$amani->getUuid() => ['Delete Amani Kimaro', 'Delete the person'],
            '/team/departments/'.$sales->getUuid() => ['Delete Sales', 'Delete the department'],
            '/team/offices/'.$office->getUuid() => ['Delete Head office', 'Delete the office'],
        ] as $record => [$title, $button]) {
            $page = $this->browser->request('GET', $record.'/configure');
            self::assertSame($record.'/delete', $page->filter('[data-danger]')->selectLink('Delete…')->attr('href'), $record);
            $page = $this->browser->request('GET', $record.'/delete');
            self::assertSame($title, trim($page->filter('h1')->text()));
            self::assertCount(1, $page->selectButton($button));
        }
    }

    public function testAdminsSeeNoDangerNoDeletePageAndNoDeletions(): void
    {
        $guide = (new Position())->setName('Guide')->setGrants([]);
        $this->em()->persist($guide);
        $this->signedInAs($this->person('Baraka', TierEnum::Admin));

        $page = $this->browser->request('GET', '/team/positions/'.$guide->getUuid().'/configure');
        self::assertCount(0, $page->filter('[data-danger]'));
        $this->browser->request('GET', '/team/positions/'.$guide->getUuid().'/delete');
        self::assertResponseStatusCodeSame(403);
        $this->browser->request('POST', '/team/positions/'.$guide->getUuid().'/delete', ['reference' => 'Guide']);
        self::assertResponseStatusCodeSame(403);
        $page = $this->browser->request('GET', '/settings/organization');
        self::assertCount(0, $page->filter('nav.tabs a[href="/settings/deletions"]'));
        $this->browser->request('GET', '/settings/deletions');
        self::assertResponseStatusCodeSame(403);
    }

    public function testASuperAdminIsNotOfferedTheirOwnDelete(): void
    {
        $neema = $this->person('Neema', TierEnum::SuperAdmin);
        $this->signedInAs($neema);

        $page = $this->browser->request('GET', '/team/'.$neema->getUuid().'/configure');
        self::assertCount(0, $page->filter('[data-danger]'));
        $this->browser->request('GET', '/team/'.$neema->getUuid().'/delete');
        self::assertResponseStatusCodeSame(403);
    }

    /**
     * @return list<string>
     */
    private function lines(Crawler $page, string $which): array
    {
        return $page->filter('[data-'.$which.'] .fact span')->each(static fn (Crawler $line): string => trim($line->text()));
    }

    private function person(string $name, TierEnum $tier, ?Position $position = null): User
    {
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
