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
use Vivutio\Bundle\IdentityBundle\Entity\User;
use Vivutio\Bundle\IdentityBundle\Enum\TierEnum;
use Vivutio\Bundle\IdentityBundle\Exception\InvalidDepartmentException;
use Vivutio\Bundle\IdentityBundle\Exception\InvalidOfficeException;
use Vivutio\Bundle\IdentityBundle\Service\DepartmentService;
use Vivutio\Bundle\IdentityBundle\Service\OfficeService;
use Vivutio\Bundle\IdentityBundle\Service\UserService;

/**
 * Offices, the core's places (vivutio DECISIONS, 2 October 2026), with
 * uhifadhi's rules for stations and postings: somebody is posted at one
 * place, and a department is the organization's or sits at an office, so a
 * person belongs to and supports the organization's departments or those of
 * the office they are posted at.
 */
final class WhereSomebodyIsPostedTest extends MigrationsTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate();
    }

    public function testAnOfficeIsNamedOnceInARealCity(): void
    {
        $office = $this->offices()->create('Head office', 'Arusha', 'Tanzania');

        self::assertSame(['Head office', 'Arusha', 'Tanzania'], [$office->getName(), $office->getCity(), $office->getCountry()]);

        foreach ([['  ', 'Arusha', 'Tanzania', 'name'], ['head office', 'Moshi', 'Tanzania', 'name'], ['Sales office', ' ', 'Tanzania', 'city']] as [$name, $city, $country, $field]) {
            try {
                $this->offices()->create($name, $city, $country);
                self::fail('"'.$name.'" in "'.$city.'" was accepted');
            } catch (InvalidOfficeException $refusal) {
                self::assertSame($field, $refusal->field);
            }
        }
    }

    /** One posting, kept with the day it began. */
    public function testSomebodyIsPostedAtOneOfficeFromADay(): void
    {
        $arusha = $this->offices()->create('Head office', 'Arusha', 'Tanzania');
        $nairobi = $this->offices()->create('Sales office', 'Nairobi', 'Kenya');
        $amani = $this->staff('Amani');

        $this->accounts()->changePosting($amani, $arusha);
        self::assertTrue($amani->isPostedAt($arusha));
        $since = $amani->getPostedSince();
        self::assertNotNull($since);

        $this->accounts()->changePosting($amani, $arusha);
        self::assertSame($since, $amani->getPostedSince(), 'posting again where they are changes nothing');

        $this->accounts()->changePosting($amani, $nairobi);
        self::assertTrue($amani->isPostedAt($nairobi));

        $this->accounts()->changePosting($amani, null);
        self::assertTrue($amani->isPostedAt(null));
        self::assertNull($amani->getPostedSince());
    }

    /** The organization's departments first, then each office's. */
    public function testADepartmentIsTheOrganizationsOrAnOffices(): void
    {
        $arusha = $this->offices()->create('Head office', 'Arusha', 'Tanzania');
        $finance = $this->departments()->create('Finance');
        $sales = $this->departments()->create('Sales', $arusha);

        self::assertTrue($finance->sitsAt(null));
        self::assertTrue($sales->sitsAt($arusha));
    }

    /** Two offices may each have a Sales; one office may not have two, nor may the organization. */
    public function testADepartmentsNameIsOnceWhereItSits(): void
    {
        $arusha = $this->offices()->create('Head office', 'Arusha', 'Tanzania');
        $nairobi = $this->offices()->create('Sales office', 'Nairobi', 'Kenya');
        $this->departments()->create('Sales', $arusha);
        $this->departments()->create('Sales', $nairobi);
        $this->departments()->create('Sales');

        $this->expectException(InvalidDepartmentException::class);
        $this->departments()->create('sales', $arusha);
    }

    public function testSomebodyBelongsToTheOrganizationsDepartmentsOrTheirOfficesOnly(): void
    {
        $arusha = $this->offices()->create('Head office', 'Arusha', 'Tanzania');
        $nairobi = $this->offices()->create('Sales office', 'Nairobi', 'Kenya');
        $finance = $this->departments()->create('Finance');
        $arushaSales = $this->departments()->create('Sales', $arusha);
        $nairobiSales = $this->departments()->create('Sales', $nairobi);
        $amani = $this->staff('Amani');
        $this->accounts()->changePosting($amani, $arusha);

        $this->accounts()->changeDepartments($amani, $finance, [$arushaSales]);
        $this->accounts()->changeDepartments($amani, $arushaSales, [$finance]);

        try {
            $this->accounts()->changeDepartments($amani, $nairobiSales, []);
            self::fail('a department of another office was accepted');
        } catch (InvalidDepartmentException $refusal) {
            self::assertSame('department', $refusal->field);
        }

        $this->expectException(InvalidDepartmentException::class);
        $this->accounts()->changeDepartments($amani, $finance, [$nairobiSales]);
    }

    /** Moving somebody to another office leaves no department of the old one behind: they are asked for anew. */
    public function testPostingElsewhereIsRefusedWhileTheyBelongToTheOldOfficesDepartment(): void
    {
        $arusha = $this->offices()->create('Head office', 'Arusha', 'Tanzania');
        $nairobi = $this->offices()->create('Sales office', 'Nairobi', 'Kenya');
        $arushaSales = $this->departments()->create('Sales', $arusha);
        $amani = $this->staff('Amani');
        $this->accounts()->changePosting($amani, $arusha);
        $this->accounts()->changeDepartments($amani, $arushaSales, []);

        $this->expectException(InvalidDepartmentException::class);
        $this->accounts()->changePosting($amani, $nairobi);
    }

    private function staff(string $name): User
    {
        $user = (new User())
            ->setEmail(strtolower($name).'@vivutio-camps.example')
            ->setFirstName($name)
            ->setLastName('Kimaro')
            ->setTier(TierEnum::Staff)
            ->setPassword('x');
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $em->persist($user);
        $em->flush();

        return $user;
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
}
