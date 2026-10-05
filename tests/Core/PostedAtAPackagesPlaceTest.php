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
use Vivutio\Bundle\IdentityBundle\Entity\User;
use Vivutio\Bundle\IdentityBundle\Enum\TierEnum;
use Vivutio\Bundle\IdentityBundle\Exception\InvalidDepartmentException;
use Vivutio\Bundle\IdentityBundle\Exception\InvalidPlaceException;
use Vivutio\Bundle\IdentityBundle\Service\DepartmentService;
use Vivutio\Bundle\IdentityBundle\Service\OfficeService;
use Vivutio\Bundle\IdentityBundle\Service\PlaceDirectoryService;
use Vivutio\Bundle\IdentityBundle\Service\UserService;
use Vivutio\Contracts\Place\PlaceInterface;
use Vivutio\Core\Tests\Application\NotesModule\NotesPlaces;

/**
 * A package's records as places (vivutio DECISIONS, 2 October 2026: a camp's
 * staff are posted at the property itself, which the module offers as a place
 * to post people, and a property has no second record in the core). Played
 * here by the stand-in package's notice boards.
 */
final class PostedAtAPackagesPlaceTest extends MigrationsTestCase
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

    public function testSomebodyIsPostedAtAPlaceAPackageOffers(): void
    {
        $amani = $this->person('Amani');
        $board = NotesPlaces::board(NotesPlaces::KITCHEN);

        $this->accounts()->changePosting($amani, $board);

        self::assertTrue($amani->isPostedAt($board));
        self::assertNotNull($amani->getPostedSince());
        self::assertSame('Kitchen board', $this->places()->postingOf($amani)?->getName());
    }

    public function testADepartmentSitsAtAPackagesPlaceAndOnlyThosePostedThereBelong(): void
    {
        $board = NotesPlaces::board(NotesPlaces::KITCHEN);
        $kitchen = $this->departments()->create('Kitchen', $board);
        $amani = $this->person('Amani');

        self::assertTrue($kitchen->sitsAt($board));
        self::assertSame('Kitchen board', $this->places()->placeOf($kitchen)?->getName());

        try {
            $this->accounts()->changeDepartments($amani, $kitchen, []);
            self::fail('somebody posted nowhere joined a department that sits at a place');
        } catch (InvalidDepartmentException $refusal) {
            self::assertStringContainsString('Kitchen board', $refusal->getMessage());
        }

        $this->accounts()->changePosting($amani, $board);
        $this->accounts()->changeDepartments($amani, $kitchen, []);
        self::assertSame('Kitchen', $amani->getDepartment()?->getName());
    }

    /** A place is offered by its kind's source, or it is no place at all. */
    public function testAPlaceNoSourceOffersIsRefused(): void
    {
        $amani = $this->person('Amani');

        foreach ([NotesPlaces::board(NotesPlaces::RETIRED), $this->stranger()] as $place) {
            try {
                $this->accounts()->changePosting($amani, $place);
                self::fail($place->getName().' was accepted');
            } catch (InvalidPlaceException $refusal) {
                self::assertSame('posted_at', $refusal->field);
            }
        }

        $this->expectException(InvalidPlaceException::class);
        $this->departments()->create('Kitchen', $this->stranger());
    }

    public function testThePositionCardOffersEveryPlaceUnderItsKind(): void
    {
        $arusha = $this->offices()->create('Head office', 'Arusha', 'Tanzania');
        $amani = $this->person('Amani');
        $this->signedInAs($this->person('Baraka', TierEnum::Admin));

        $page = $this->browser->request('GET', '/team/'.$amani->getUuid().'/configure');

        $groups = $page->filter('select[name="posted_at"] optgroup');
        self::assertSame(['Offices', 'Notice boards'], $groups->each(static fn (Crawler $group): string => (string) $group->attr('label')));
        self::assertSame(['office:'.$arusha->getUuid(), 'board:'.NotesPlaces::KITCHEN], $groups->filter('option')->each(static fn (Crawler $option): string => (string) $option->attr('value')));

        $this->browser->submit($page->selectButton('Save position')->form(['posted_at' => 'board:'.NotesPlaces::KITCHEN]));

        self::assertResponseRedirects('/team/'.$amani->getUuid().'/configure');
        self::assertStringContainsString('Kitchen board', $this->browser->request('GET', '/team/'.$amani->getUuid())->filter('[data-posted-at]')->text());
    }

    /** A place no longer offered keeps its name; one whose package is gone says so, until a new posting is chosen. */
    public function testAPostingIsNamedEvenWhenItsPlaceIsNoLongerOffered(): void
    {
        $retired = $this->person('Amani')->setPosting(NotesPlaces::board(NotesPlaces::RETIRED), new \DateTimeImmutable());
        $gone = $this->person('Elia')->setPosting($this->stranger(), new \DateTimeImmutable());
        $this->em()->flush();
        $this->signedInAs($this->person('Baraka', TierEnum::Admin));

        self::assertStringContainsString('Retired board', $this->browser->request('GET', '/team/'.$retired->getUuid())->filter('[data-posted-at]')->text());
        self::assertStringContainsString('A place no installed package offers', $this->browser->request('GET', '/team/'.$gone->getUuid())->filter('[data-posted-at]')->text());
    }

    /** The organization's departments first, then each place's, offices and a package's alike. */
    public function testTheDepartmentsRegisterGroupsByEveryKindOfPlace(): void
    {
        $arusha = $this->offices()->create('Head office', 'Arusha', 'Tanzania');
        $this->departments()->create('Kitchen', NotesPlaces::board(NotesPlaces::KITCHEN));
        $this->departments()->create('Sales', $arusha);
        $this->departments()->create('Finance');
        $this->signedInAs($this->person('Baraka', TierEnum::Admin));

        $page = $this->browser->request('GET', '/team/departments');

        self::assertSame(['The organization', 'Head office', 'Kitchen board'], $page->filter('tr[data-group]')->each(static fn (Crawler $row): string => trim($row->text())));
    }

    private function stranger(): PlaceInterface
    {
        return new class implements PlaceInterface {
            public function getPlaceKind(): string
            {
                return 'shed';
            }

            public function getPlaceId(): string
            {
                return '0199b0a0-0000-7000-8000-00000000c001';
            }

            public function getName(): string
            {
                return 'Garden shed';
            }
        };
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

    private function signedInAs(User $user): void
    {
        $this->browser->restart();
        $this->browser->loginUser($user);
    }

    private function places(): PlaceDirectoryService
    {
        $service = static::getContainer()->get('test_public.places');
        self::assertInstanceOf(PlaceDirectoryService::class, $service);

        return $service;
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
