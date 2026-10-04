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
use Vivutio\Bundle\IdentityBundle\Entity\User;
use Vivutio\Bundle\IdentityBundle\Enum\TierEnum;
use Vivutio\Bundle\IdentityBundle\Service\DepartmentService;
use Vivutio\Bundle\IdentityBundle\Service\OfficeService;
use Vivutio\Bundle\IdentityBundle\Service\UserService;

/**
 * The offices and postings on screen, after uhifadhi's stations: one fact
 * written in two places, the office's page and the person's. Offices are read
 * with offices.read and changed by the tiers alone.
 */
final class TheOfficesTest extends MigrationsTestCase
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

    public function testTheRegisterListsEachOfficeWithWhoIsPostedThere(): void
    {
        $arusha = $this->offices()->create('Head office', 'Arusha', 'Tanzania');
        $this->offices()->create('Sales office', 'Nairobi', 'Kenya');
        $this->departments()->create('Sales', $arusha);
        $this->accounts()->changePosting($this->person('Amani'), $arusha);
        $this->signedInAs($this->person('Baraka', TierEnum::Admin));

        $page = $this->browser->request('GET', '/team/offices');

        self::assertResponseIsSuccessful();
        self::assertSame(['Head office', 'Sales office'], $page->filter('tr[data-office]')->each(static fn (Crawler $row): string => (string) $row->attr('data-office')));
        $row = $page->filter('tr[data-office="Head office"]');
        self::assertSame('Arusha, Tanzania', trim($row->filter('[data-where]')->text()));
        self::assertSame('1 posted', trim($row->filter('[data-posted]')->text()));
        self::assertSame('1 department', trim($row->filter('[data-departments]')->text()));
        self::assertSame('/team/offices/'.$arusha->getUuid(), $row->selectLink('Open')->attr('href'));
        self::assertSame('/team/offices', $this->browser->request('GET', '/')->filter('nav.menu')->selectLink('Offices')->attr('href'));
    }

    public function testTheOfficesPageSaysWhoIsPostedThereAndWhichDepartmentsSitThere(): void
    {
        $arusha = $this->offices()->create('Head office', 'Arusha', 'Tanzania');
        $this->departments()->create('Sales', $arusha);
        $this->accounts()->changePosting($this->person('Amani'), $arusha);
        $this->signedInAs($this->person('Baraka', TierEnum::Admin));

        $page = $this->browser->request('GET', '/team/offices/'.$arusha->getUuid());

        self::assertSame('Head office', trim($page->filter('h1')->text()));
        self::assertSame(['Amani Kimaro'], $page->filter('[data-posted] [data-person]')->each(static fn (Crawler $row): string => (string) $row->attr('data-person')));
        self::assertSame(['Sales'], $page->filter('[data-departments] [data-department]')->each(static fn (Crawler $row): string => (string) $row->attr('data-department')));
    }

    public function testATierAddsAnOfficeAndChangesIt(): void
    {
        $this->signedInAs($this->person('Baraka', TierEnum::Admin));

        $page = $this->browser->request('GET', '/team/offices');
        $this->browser->submit($page->selectButton('Add the office')->form(['name' => 'Head office', 'city' => 'Arusha', 'country' => 'Tanzania']));
        $office = $this->find(Office::class, 'Head office');
        self::assertResponseRedirects('/team/offices/'.$office->getUuid());

        $page = $this->browser->request('GET', '/team/offices/'.$office->getUuid().'/configure');
        $this->browser->submit($page->selectButton('Save office')->form(['name' => 'Head office', 'city' => 'Moshi', 'country' => 'Tanzania']));
        self::assertResponseRedirects('/team/offices/'.$office->getUuid());
        self::assertSame('Moshi', $this->find(Office::class, 'Head office')->getCity());
    }

    public function testStaffChangeNoOffice(): void
    {
        $arusha = $this->offices()->create('Head office', 'Arusha', 'Tanzania');
        $this->signedInAs($this->person('Elia'));

        $this->browser->request('GET', '/team/offices');
        self::assertResponseStatusCodeSame(403);
        $this->browser->request('GET', '/team/offices/'.$arusha->getUuid().'/configure');
        self::assertResponseStatusCodeSame(403);
    }

    /** Moving somebody and choosing the new office's departments is one save, as the approved Position card has it. */
    public function testPostingAndDepartmentsAreSavedTogetherWithTheSeat(): void
    {
        $arusha = $this->offices()->create('Head office', 'Arusha', 'Tanzania');
        $nairobi = $this->offices()->create('Sales office', 'Nairobi', 'Kenya');
        $arushaSales = $this->departments()->create('Sales', $arusha);
        $nairobiSales = $this->departments()->create('Sales', $nairobi);
        $amani = $this->person('Amani');
        $this->accounts()->changePosting($amani, $arusha);
        $this->accounts()->changeDepartments($amani, $arushaSales, []);
        $this->signedInAs($this->person('Baraka', TierEnum::Admin));

        $page = $this->browser->request('GET', '/team/'.$amani->getUuid().'/configure');
        $this->browser->submit($page->selectButton('Save position')->form(['posted_at' => (string) $nairobi->getUuid(), 'department' => (string) $nairobiSales->getUuid()]));

        self::assertResponseRedirects('/team/'.$amani->getUuid().'/configure');
        $amani = $this->reloaded($amani);
        self::assertSame('Sales office', $amani->getPostedAt()?->getName());
        self::assertSame($nairobiSales->getId(), $amani->getDepartment()?->getId());

        $record = $this->browser->request('GET', '/team/'.$amani->getUuid());
        self::assertStringContainsString('Sales office', $record->filter('[data-posted-at]')->text());
    }

    /** The organization's departments first, then each office's under its name. */
    public function testTheDepartmentsRegisterGroupsByWhereEachSits(): void
    {
        $arusha = $this->offices()->create('Head office', 'Arusha', 'Tanzania');
        $this->departments()->create('Sales', $arusha);
        $this->departments()->create('Finance');
        $this->signedInAs($this->person('Baraka', TierEnum::Admin));

        $page = $this->browser->request('GET', '/team/departments');

        self::assertSame(['The organization', 'Head office'], $page->filter('tr[data-group]')->each(static fn (Crawler $row): string => trim($row->text())));
        self::assertSame(['Finance', 'Sales'], $page->filter('tr[data-department]')->each(static fn (Crawler $row): string => (string) $row->attr('data-department')));
    }

    public function testADepartmentIsMovedToAnOfficeOnItsConfigurePage(): void
    {
        $arusha = $this->offices()->create('Head office', 'Arusha', 'Tanzania');
        $sales = $this->departments()->create('Sales');
        $this->signedInAs($this->person('Baraka', TierEnum::Admin));

        $page = $this->browser->request('GET', '/team/departments/'.$sales->getUuid().'/configure');
        $this->browser->submit($page->selectButton('Save department')->form(['office' => (string) $arusha->getUuid()]));

        self::assertResponseRedirects('/team/departments/'.$sales->getUuid());
        self::assertSame('Head office', $this->find(Department::class, 'Sales')->getOffice()?->getName());
    }

    private function person(string $name, TierEnum $tier = TierEnum::Staff): User
    {
        $user = (new User())
            ->setEmail(strtolower($name).'@vivutio-camps.example')
            ->setFirstName($name)
            ->setLastName('Kimaro')
            ->setTier($tier)
            ->setPassword('a hash, never a password');
        $this->em()->persist($user);
        $this->em()->flush();

        return $user;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function find(string $class, string $name): object
    {
        $this->em()->clear();
        $found = $this->em()->getRepository($class)->findOneBy(['name' => $name]);
        self::assertInstanceOf($class, $found);

        return $found;
    }

    private function reloaded(User $user): User
    {
        $this->em()->clear();
        $fresh = $this->em()->find(User::class, $user->getId());
        self::assertInstanceOf(User::class, $fresh);

        return $fresh;
    }

    private function signedInAs(User $user): void
    {
        $this->browser->restart();
        $this->browser->loginUser($user);
    }

    private function offices(): OfficeService
    {
        $service = static::getContainer()->get('test_public.offices');
        self::assertInstanceOf(OfficeService::class, $service);

        return $service;
    }

    private function departments(): DepartmentService
    {
        $service = static::getContainer()->get('test_public.departments');
        self::assertInstanceOf(DepartmentService::class, $service);

        return $service;
    }

    private function accounts(): UserService
    {
        $service = static::getContainer()->get('test_public.accounts');
        self::assertInstanceOf(UserService::class, $service);

        return $service;
    }

    private function em(): EntityManagerInterface
    {
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $em);

        return $em;
    }
}
