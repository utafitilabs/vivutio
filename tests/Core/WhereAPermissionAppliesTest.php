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
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Vivutio\Bundle\IdentityBundle\Entity\Department;
use Vivutio\Bundle\IdentityBundle\Entity\Position;
use Vivutio\Bundle\IdentityBundle\Entity\User;
use Vivutio\Bundle\IdentityBundle\Enum\TierEnum;
use Vivutio\Bundle\IdentityBundle\Service\OfficeService;
use Vivutio\Contracts\Place\PlacedInterface;
use Vivutio\Contracts\Place\PlaceInterface;
use Vivutio\Core\Tests\Application\Kernel;
use Vivutio\Core\Tests\Application\NotesModule\NotesPlaces;
use Vivutio\Core\Tests\Application\NotesModule\NotesReach;

/**
 * Where a permission applies (the security blueprint's check, "reach covers
 * the subject's place?"): after a pair is otherwise held, a subject that is a
 * place, or belongs to one, is asked of every reach source; one "no" refuses,
 * with its reason. The tiers are never narrowed. Played by the stand-in
 * package's notice boards.
 */
final class WhereAPermissionAppliesTest extends MigrationsTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate();
        NotesReach::$covering = [];
    }

    public function testAPairOnAPlaceHoldsWhereTheReachCoversIt(): void
    {
        $amani = $this->staff('Amani');
        $kitchen = NotesPlaces::board(NotesPlaces::KITCHEN);

        self::assertTrue($this->may($amani, 'notes.read', $kitchen), 'a person the source names nothing of reaches every board');

        NotesReach::$covering[(string) $amani->getUuid()] = [NotesPlaces::KITCHEN];
        self::assertTrue($this->may($amani, 'notes.read', $kitchen));

        NotesReach::$covering[(string) $amani->getUuid()] = [NotesPlaces::RETIRED];
        self::assertFalse($this->may($amani, 'notes.read', $kitchen));
        self::assertContains('Amani Kimaro\'s reach does not cover Kitchen board.', $this->reasons($amani, 'notes.read', $kitchen));
        self::assertTrue($this->may($amani, 'notes.read'), 'without a subject there is no place to narrow to');
    }

    public function testARecordIsAskedAboutWhereItIs(): void
    {
        $amani = $this->staff('Amani');
        NotesReach::$covering[(string) $amani->getUuid()] = [NotesPlaces::RETIRED];
        $note = new class implements PlacedInterface {
            public function placedAt(): PlaceInterface
            {
                return NotesPlaces::board(NotesPlaces::KITCHEN);
            }
        };

        self::assertFalse($this->may($amani, 'notes.read', $note));
    }

    public function testTheTiersAreNeverNarrowed(): void
    {
        $admin = $this->staff('Baraka')->setTier(TierEnum::Admin);
        $this->em()->flush();
        NotesReach::$covering[(string) $admin->getUuid()] = [];

        self::assertTrue($this->may($admin, 'notes.read', NotesPlaces::board(NotesPlaces::KITCHEN)));
    }

    /** A place no source has a say over is not narrowed: no package answers for offices here. */
    public function testAPlaceNoSourceAnswersForIsNotNarrowed(): void
    {
        $amani = $this->staff('Amani');
        NotesReach::$covering[(string) $amani->getUuid()] = [];
        $office = static::getContainer()->get('test_public.offices');
        self::assertInstanceOf(OfficeService::class, $office);

        self::assertTrue($this->may($amani, 'notes.read', $office->create('Head office', 'Arusha', 'Tanzania')));
    }

    /** Reach narrows what is otherwise held; it never grants what is not. */
    public function testReachNeverGrants(): void
    {
        $amani = $this->staff('Amani', []);

        self::assertFalse($this->may($amani, 'notes.read', NotesPlaces::board(NotesPlaces::KITCHEN)));
    }

    /**
     * @param list<string> $grants
     */
    private function staff(string $name, array $grants = ['notes.read']): User
    {
        $department = (new Department())->setName($name.'\'s department')->setAllows(['notes.read']);
        $position = (new Position())->setName($name.'\'s seat')->setGrants($grants);
        $user = (new User())
            ->setEmail(strtolower($name).'@vivutio-camps.example')
            ->setFirstName($name)
            ->setLastName('Kimaro')
            ->setTier(TierEnum::Staff)
            ->setPosition($position)
            ->setDepartment($department)
            ->setPassword('x');
        $this->em()->persist($department);
        $this->em()->persist($position);
        $this->em()->persist($user);
        $this->em()->flush();

        return $user;
    }

    private function may(User $user, string $pair, mixed $subject = null): bool
    {
        $security = static::getContainer()->get(Kernel::SECURITY);
        self::assertInstanceOf(Security::class, $security);

        return $security->isGrantedForUser($user, $pair, $subject);
    }

    /**
     * @return list<string>
     */
    private function reasons(User $user, string $pair, mixed $subject): array
    {
        $voter = static::getContainer()->get('test_public.grant_voter');
        self::assertInstanceOf(VoterInterface::class, $voter);
        $vote = new Vote();
        $voter->vote(new UsernamePasswordToken($user, 'main', $user->getRoles()), $subject, [$pair], $vote);

        return $vote->reasons;
    }

    private function em(): EntityManagerInterface
    {
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $em);

        return $em;
    }
}
