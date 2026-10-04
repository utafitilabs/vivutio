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
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\DomCrawler\Form;
use Vivutio\Bundle\IdentityBundle\Entity\Department;
use Vivutio\Bundle\IdentityBundle\Entity\Position;
use Vivutio\Bundle\IdentityBundle\Entity\User;
use Vivutio\Bundle\IdentityBundle\Enum\TierEnum;
use Vivutio\Bundle\IdentityBundle\Service\DepartmentService;

/**
 * The departments, ported from uhifadhi's register, record and configure
 * page: who belongs, who heads it, what it allows. Read with
 * departments.read; changed by the tiers alone, since what a department
 * allows lifts what Staff may do.
 */
final class TheDepartmentsTest extends MigrationsTestCase
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

    public function testTheRegisterListsEachDepartmentWithItsHeadMembersAndWhatItAllows(): void
    {
        $housekeeping = $this->departments()->create('Housekeeping');
        $kitchen = $this->departments()->create('Kitchen');
        $this->departments()->changeAllows($kitchen, ['notes.read']);
        $head = $this->position('Head housekeeper');
        $this->person('Amani', $housekeeping, $head);
        $this->person('Elia', $housekeeping, null, [$kitchen]);
        $this->departments()->changeHead($housekeeping, $head);
        $this->signedInAs($this->person('Baraka', null, null, [], TierEnum::Admin));

        $page = $this->browser->request('GET', '/team/departments');

        self::assertResponseIsSuccessful();
        self::assertSame(['Housekeeping', 'Kitchen'], $page->filter('tr[data-department]')->each(static fn (Crawler $row): string => (string) $row->attr('data-department')));
        $row = $page->filter('tr[data-department="Housekeeping"]');
        self::assertSame('Head housekeeper · Amani Kimaro', trim((string) preg_replace('/\s+/', ' ', $row->filter('[data-head]')->text())));
        self::assertSame('2 belong', trim($row->filter('[data-members]')->text()));
        self::assertSame('Allows nothing yet', trim($row->filter('[data-allows]')->text()));
        self::assertSame('0 belong · 1 supports', trim($page->filter('tr[data-department="Kitchen"] [data-members]')->text()));
        self::assertSame('Allows 1', trim($page->filter('tr[data-department="Kitchen"] [data-allows]')->text()));
        self::assertSame('/team/departments/'.$housekeeping->getUuid(), $row->selectLink('Open')->attr('href'));
    }

    public function testTheMenuLeadsToTheDepartmentsForWhoeverReadsThem(): void
    {
        $this->signedInAs($this->person('Baraka', null, null, [], TierEnum::Admin));
        self::assertSame('/team/departments', $this->browser->request('GET', '/')->filter('nav.menu')->selectLink('Departments')->attr('href'));

        $this->signedInAs($this->person('Elia', null, $this->position('Clerk', ['directory.read'])));
        self::assertCount(0, $this->browser->request('GET', '/')->filter('nav.menu')->selectLink('Departments'));
        $this->browser->request('GET', '/team/departments');
        self::assertResponseStatusCodeSame(403);
    }

    public function testTheRecordSaysWhoBelongsWhoSupportsAndWhatItAllows(): void
    {
        $housekeeping = $this->departments()->create('Housekeeping');
        $kitchen = $this->departments()->create('Kitchen');
        $this->departments()->changeAllows($housekeeping, ['notes.read']);
        $head = $this->position('Head housekeeper');
        $this->person('Amani', $housekeeping, $head);
        $this->departments()->changeHead($housekeeping, $head);
        $this->person('Jabiri', $kitchen, null, [$housekeeping]);
        $this->signedInAs($this->person('Baraka', null, null, [], TierEnum::Admin));

        $page = $this->browser->request('GET', '/team/departments/'.$housekeeping->getUuid());

        self::assertSame('Housekeeping', trim($page->filter('h1')->text()));
        self::assertSame(['Amani Kimaro', 'Jabiri Kimaro'], $page->filter('[data-members] [data-member]')->each(static fn (Crawler $row): string => (string) $row->attr('data-member')));
        self::assertStringContainsString('heads it', $page->filter('[data-member="Amani Kimaro"]')->text());
        self::assertStringContainsString('supports, from Kitchen', $page->filter('[data-member="Jabiri Kimaro"]')->text());
        self::assertSame(['notes.read'], $page->filter('[data-matrix] [data-pair][data-on]')->each(static fn (Crawler $cell): string => (string) $cell->attr('data-pair')));
    }

    /** A department that allows nothing says so loudly, with how many people it leaves without a module. */
    public function testADepartmentThatAllowsNothingSaysSo(): void
    {
        $housekeeping = $this->departments()->create('Housekeeping');
        $this->person('Amani', $housekeeping, null);
        $this->signedInAs($this->person('Baraka', null, null, [], TierEnum::Admin));

        $page = $this->browser->request('GET', '/team/departments/'.$housekeeping->getUuid());

        self::assertStringContainsString('Housekeeping allows nothing yet: the 1 person who belongs to it reaches no module through it.', $page->filter('.notice.warn')->text());
    }

    public function testATierAddsADepartmentAndGoesOnToConfigureIt(): void
    {
        $this->signedInAs($this->person('Baraka', null, null, [], TierEnum::Admin));

        $page = $this->browser->request('GET', '/team/departments');
        $this->browser->submit($page->selectButton('Add the department')->form(['name' => 'Front office']));

        $added = $this->em()->getRepository(Department::class)->findOneBy(['name' => 'Front office']);
        self::assertInstanceOf(Department::class, $added);
        self::assertResponseRedirects('/team/departments/'.$added->getUuid().'/configure');
    }

    public function testConfiguringNamesItChoosesItsHeadAndSaysWhatItAllows(): void
    {
        $housekeeping = $this->departments()->create('Housekeeping');
        $head = $this->position('Head housekeeper');
        $this->person('Amani', $housekeeping, $head);
        $this->signedInAs($this->person('Baraka', null, null, [], TierEnum::Admin));

        $page = $this->browser->request('GET', '/team/departments/'.$housekeeping->getUuid().'/configure');
        $form = $page->selectButton('Save department')->form(['name' => 'Housekeeping and laundry', 'head' => (string) $head->getUuid()]);
        $this->tick($form, 'allows', 'notes.read');
        $this->browser->submit($form);

        self::assertResponseRedirects('/team/departments/'.$housekeeping->getUuid());
        $housekeeping = $this->reloaded($housekeeping);
        self::assertSame('Housekeeping and laundry', $housekeeping->getName());
        self::assertSame('Head housekeeper', $housekeeping->getHead()?->getName());
        self::assertSame(['notes.read'], $housekeeping->getAllows());
    }

    /** Each position offered as head says whether it can be, and why not. */
    public function testTheHeadsOnOfferSayWhyNot(): void
    {
        $housekeeping = $this->departments()->create('Housekeeping');
        $kitchen = $this->departments()->create('Kitchen');
        $this->person('Elia', $kitchen, $this->position('Chef'));
        $guide = $this->position('Guide');
        $this->person('Jabiri', $housekeeping, $guide);
        $this->person('Neema', $housekeeping, $guide);
        $this->signedInAs($this->person('Baraka', null, null, [], TierEnum::Admin));

        $page = $this->browser->request('GET', '/team/departments/'.$housekeeping->getUuid().'/configure');

        self::assertStringContainsString('belongs to Kitchen', $page->filter('select[name="head"] option')->reduce(static fn (Crawler $o): bool => str_starts_with($o->text(), 'Chef'))->text());
        self::assertNotNull($page->filter('select[name="head"] option')->reduce(static fn (Crawler $o): bool => str_starts_with($o->text(), 'Guide'))->attr('disabled'), 'a position two people hold cannot head');
    }

    public function testStaffChangeNoDepartment(): void
    {
        $housekeeping = $this->departments()->create('Housekeeping');
        $this->signedInAs($this->person('Elia', $housekeeping, $this->position('Clerk', ['departments.read'])));

        self::assertCount(0, $this->browser->request('GET', '/team/departments')->selectButton('Add the department'));
        $this->browser->request('GET', '/team/departments/'.$housekeeping->getUuid().'/configure');
        self::assertResponseStatusCodeSame(403);
        $this->browser->request('POST', '/team/departments', ['name' => 'Taken over']);
        self::assertResponseStatusCodeSame(403);
    }

    /** The approved Position card: belongs to one, supports any number, saved with the seat. */
    public function testAPersonsDepartmentsAreSetWithTheirPosition(): void
    {
        $housekeeping = $this->departments()->create('Housekeeping');
        $kitchen = $this->departments()->create('Kitchen');
        $amani = $this->person('Amani', null, null);
        $this->signedInAs($this->person('Baraka', null, null, [], TierEnum::Admin));

        $page = $this->browser->request('GET', '/team/'.$amani->getUuid().'/configure');
        $form = $page->selectButton('Save position')->form(['department' => (string) $housekeeping->getUuid()]);
        $this->tick($form, 'supports', (string) $kitchen->getUuid());
        $this->browser->submit($form);

        $amani = $this->reloadedPerson($amani);
        self::assertSame('Housekeeping', $amani->getDepartment()?->getName());
        self::assertSame(['Kitchen'], array_map(static fn (Department $d): string => $d->getName(), $amani->getSupports()->toArray()));

        $record = $this->browser->request('GET', '/team/'.$amani->getUuid());
        self::assertStringContainsString('Belongs to Housekeeping', $record->filter('[data-department]')->text());
    }

    private function tick(Form $form, string $name, string $value): void
    {
        $boxes = $form[$name];
        self::assertIsArray($boxes);

        foreach ($boxes as $box) {
            self::assertInstanceOf(ChoiceFormField::class, $box);
            if ($value === $box->availableOptionValues()[0]) {
                $box->tick();

                return;
            }
        }

        self::fail('No box for '.$value);
    }

    /**
     * @param list<Department> $supports
     */
    private function person(string $name, ?Department $department, ?Position $position, array $supports = [], TierEnum $tier = TierEnum::Staff): User
    {
        $user = (new User())
            ->setEmail(strtolower($name).'@vivutio-camps.example')
            ->setFirstName($name)
            ->setLastName('Kimaro')
            ->setTier($tier)
            ->setPosition($position)
            ->setDepartment($department)
            ->setSupports($supports)
            ->setPassword('a hash, never a password');
        $this->em()->persist($user);
        $this->em()->flush();

        return $user;
    }

    /**
     * @param list<string> $grants
     */
    private function position(string $name, array $grants = []): Position
    {
        $position = (new Position())->setName($name)->setGrants($grants);
        $this->em()->persist($position);
        $this->em()->flush();

        return $position;
    }

    private function signedInAs(User $user): void
    {
        $this->browser->restart();
        $this->browser->loginUser($user);
    }

    private function reloaded(Department $department): Department
    {
        $this->em()->clear();
        $fresh = $this->em()->find(Department::class, $department->getId());
        self::assertInstanceOf(Department::class, $fresh);

        return $fresh;
    }

    private function reloadedPerson(User $user): User
    {
        $this->em()->clear();
        $fresh = $this->em()->find(User::class, $user->getId());
        self::assertInstanceOf(User::class, $fresh);

        return $fresh;
    }

    private function departments(): DepartmentService
    {
        $service = static::getContainer()->get('test_public.departments');
        self::assertInstanceOf(DepartmentService::class, $service);

        return $service;
    }

    private function em(): EntityManagerInterface
    {
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $em);

        return $em;
    }
}
