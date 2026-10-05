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
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Vivutio\Bundle\IdentityBundle\Entity\DeletionRecord;
use Vivutio\Bundle\IdentityBundle\Entity\Department;
use Vivutio\Bundle\IdentityBundle\Entity\Office;
use Vivutio\Bundle\IdentityBundle\Entity\Position;
use Vivutio\Bundle\IdentityBundle\Entity\User;
use Vivutio\Bundle\IdentityBundle\Enum\TierEnum;
use Vivutio\Bundle\IdentityBundle\Exception\DeletionRefusedException;
use Vivutio\Bundle\IdentityBundle\Service\DeletionService;

/**
 * A Super Admin deletes what was made by mistake or in tests, as uhifadhi
 * ruled it (28 September): a hard delete of the record and what goes with it,
 * counted first, confirmed by typing the record's reference, with one line
 * kept of who deleted what and when. Packages answer for their own rows
 * through the deletion contract; the core names none of them.
 */
final class TheDeletionsTest extends MigrationsTestCase
{
    protected function start(): void
    {
        static::bootKernel();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate();
    }

    public function testAPositionIsCountedThenDeletedAndItsHoldersKept(): void
    {
        $ranger = (new Position())->setName('Guide')->setGrants([]);
        $this->em()->persist($ranger);
        $this->person('Amani', TierEnum::Staff, $ranger);
        $this->person('Elia', TierEnum::Staff, $ranger);
        $this->signedInAs($this->person('Neema', TierEnum::SuperAdmin));

        $plan = $this->deletions()->plan($ranger);
        self::assertSame('Guide', $plan->subject->reference);
        self::assertSame(['1 position'], $plan->whatGoes());
        self::assertSame(['2 people, who keep their account without a seat'], $plan->whatStays());

        $line = $this->deletions()->delete($ranger, 'Guide');
        self::assertSame('Neema Kimaro', $line->getByName());
        self::assertSame(['1 position'], $line->getWhatWent());
        $this->em()->clear();
        self::assertNull($this->em()->getRepository(Position::class)->findOneBy(['name' => 'Guide']));
        self::assertCount(3, $this->em()->getRepository(User::class)->findAll(), 'the holders and the Super Admin all stay');
        self::assertCount(1, $this->em()->getRepository(DeletionRecord::class)->findAll());
    }

    public function testAPersonGoesWithEverythingOfTheirs(): void
    {
        $amani = $this->person('Amani', TierEnum::Staff);
        $this->signedInAs($this->person('Neema', TierEnum::SuperAdmin));

        $plan = $this->deletions()->plan($amani);
        self::assertSame('amani@vivutio-camps.example', $plan->subject->reference);
        self::assertSame('Amani Kimaro', $plan->subject->title);
        self::assertSame(['1 person'], $plan->whatGoes());

        $this->deletions()->delete($amani, 'amani@vivutio-camps.example');
        $this->em()->clear();
        self::assertNull($this->em()->getRepository(User::class)->findOneBy(['email' => 'amani@vivutio-camps.example']));
    }

    /** People posted at an office are posted nowhere; departments that sat there sit with the organization. */
    public function testAnOfficeGoesAndWhatSatThereStays(): void
    {
        $arusha = (new Office())->setName('Head office')->setCity('Arusha')->setCountry('Tanzania');
        $this->em()->persist($arusha);
        $this->em()->flush();
        $sales = (new Department())->setName('Sales')->setPlace($arusha);
        $this->em()->persist($sales);
        $amani = $this->person('Amani', TierEnum::Staff);
        $amani->setPosting($arusha, new \DateTimeImmutable('2026-10-01'));
        $this->em()->flush();
        $this->signedInAs($this->person('Neema', TierEnum::SuperAdmin));

        self::assertSame(['1 person posted there, who is then posted nowhere', '1 department sitting there, which then sits with the organization'], $this->deletions()->plan($arusha)->whatStays());
        $this->deletions()->delete($arusha, 'Head office');

        $this->em()->clear();
        $amani = $this->em()->getRepository(User::class)->findOneBy(['email' => 'amani@vivutio-camps.example']);
        self::assertInstanceOf(User::class, $amani);
        self::assertNull($amani->getPostedKind());
        $sales = $this->em()->getRepository(Department::class)->findOneBy(['name' => 'Sales']);
        self::assertInstanceOf(Department::class, $sales);
        self::assertNull($sales->getPlaceKind());
    }

    public function testOnlyASuperAdminDeletesAndOnlyWithTheReferenceTyped(): void
    {
        $guide = (new Position())->setName('Guide')->setGrants([]);
        $this->em()->persist($guide);
        $this->em()->flush();

        $this->signedInAs($this->person('Baraka', TierEnum::Admin));
        self::assertFalse($this->deletions()->mayDelete());
        try {
            $this->deletions()->delete($guide, 'Guide');
            self::fail('An Admin deleted.');
        } catch (AccessDeniedException) {
        }

        $this->signedInAs($this->person('Neema', TierEnum::SuperAdmin));
        self::assertTrue($this->deletions()->mayDelete());
        try {
            $this->deletions()->delete($guide, 'guide');
            self::fail('Deleted without the reference typed as it is.');
        } catch (DeletionRefusedException $refusal) {
            self::assertSame('Type Guide exactly, to say it is the one to delete.', $refusal->getMessage());
        }
        $this->em()->clear();
        self::assertNotNull($this->em()->getRepository(Position::class)->findOneBy(['name' => 'Guide']));
        self::assertCount(0, $this->em()->getRepository(DeletionRecord::class)->findAll());
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
        $tokens = static::getContainer()->get('security.token_storage');
        self::assertInstanceOf(TokenStorageInterface::class, $tokens);
        $tokens->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
    }

    private function deletions(): DeletionService
    {
        $service = static::getContainer()->get(DeletionService::class);
        self::assertInstanceOf(DeletionService::class, $service);

        return $service;
    }

    private function em(): EntityManagerInterface
    {
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $em);

        return $em;
    }
}
