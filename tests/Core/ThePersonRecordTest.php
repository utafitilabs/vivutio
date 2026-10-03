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
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DomCrawler\Crawler;
use Vivutio\Bundle\IdentityBundle\Entity\Position;
use Vivutio\Bundle\IdentityBundle\Entity\User;
use Vivutio\Bundle\IdentityBundle\Enum\TierEnum;
use Vivutio\Bundle\IdentityBundle\Security\AccountVoter;
use Vivutio\Core\Tests\Application\Kernel;

/**
 * A person's record, ported from uhifadhi's (GET /team/{uuid}): who they are,
 * their account, their position and what they may do right now, read from the
 * voters themselves. It follows the team list's rules: an address only with
 * personal details, and a tier only where the single-record rule allows it.
 */
final class ThePersonRecordTest extends MigrationsTestCase
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

    public function testWhoeverReadsTheDirectoryOpensARecord(): void
    {
        $amani = $this->person('Amani', TierEnum::Staff, ['directory.read'], 'Housekeeper');
        $this->signedInAs($this->person('Baraka', TierEnum::Staff, ['directory.read'], 'Guide'));

        $page = $this->browser->request('GET', $this->address($amani));

        self::assertResponseIsSuccessful();
        self::assertSame('Amani Kimaro', trim($page->filter('h1')->text()));
        self::assertSame('Housekeeper', trim($page->filter('[data-position]')->text()));
        self::assertSame('Active', trim($page->filter('[data-account]')->text()));
    }

    public function testWithoutTheDirectoryARecordIsNotOpened(): void
    {
        $amani = $this->person('Amani', TierEnum::Staff);
        $this->signedInAs($this->person('Baraka', TierEnum::Staff));

        $this->browser->request('GET', $this->address($amani));

        self::assertResponseStatusCodeSame(403);
    }

    /** An address that names nobody, or names nothing at all, is not found. */
    public function testAnAddressThatNamesNobodyIsNotFound(): void
    {
        $this->signedInAs($this->person('Neema', TierEnum::SuperAdmin));

        $this->browser->request('GET', '/team/0199a6f0-0000-7000-8000-000000000000');
        self::assertResponseStatusCodeSame(404);

        $this->browser->request('GET', '/team/not-a-uuid');
        self::assertResponseStatusCodeSame(404);
    }

    public function testTheAddressIsShownOnlyWithPersonalDetails(): void
    {
        $amani = $this->person('Amani', TierEnum::Staff);

        $this->signedInAs($this->person('Baraka', TierEnum::Staff, ['directory.read'], 'Guide'));
        self::assertStringNotContainsString('amani@', (string) $this->browser->request('GET', $this->address($amani))->html());

        $this->signedInAs($this->person('Elia', TierEnum::Staff, ['directory.read', 'personal_details.read'], 'Clerk'));
        self::assertSame('amani@vivutio-camps.example', trim($this->browser->request('GET', $this->address($amani))->filter('[data-email]')->text()));
    }

    /**
     * On a single record: a Super Admin's tier is seen by Super Admins only,
     * Admins see Admins' and Staff's, and Staff see no tier.
     */
    public function testATierIsShownAsTheSingleRecordRuleAllows(): void
    {
        $neema = $this->person('Neema', TierEnum::SuperAdmin);
        $baraka = $this->person('Baraka', TierEnum::Admin);
        $amani = $this->person('Amani', TierEnum::Staff);
        $elia = $this->person('Elia', TierEnum::Staff, ['directory.read'], 'Clerk');

        self::assertSame([true, true, true], $this->seesTiers($neema, [$neema, $baraka, $amani]));
        self::assertSame([false, true, true], $this->seesTiers($baraka, [$neema, $baraka, $amani]));
        self::assertSame([false, false, false], $this->seesTiers($elia, [$neema, $baraka, $amani]));

        $this->signedInAs($baraka);
        self::assertCount(0, $this->browser->request('GET', $this->address($neema))->filter('[data-tier]'));
        self::assertSame('Staff', trim($this->browser->request('GET', $this->address($amani))->filter('[data-tier]')->text()));
    }

    /** What a position grants, as the voters answer it right now. */
    public function testTheRecordSaysWhatThePersonMayDoRightNow(): void
    {
        $amani = $this->person('Amani', TierEnum::Staff, ['directory.read', 'personal_details.read'], 'Housekeeper');
        $this->signedInAs($this->person('Baraka', TierEnum::Admin));

        $page = $this->browser->request('GET', $this->address($amani));

        self::assertSame(['Directory: Read', 'Personal details: Read'], $this->ledger($page));
        self::assertCount(1, $page->filter('[data-grants] [data-sensitive]'), 'personal details are marked sensitive');
    }

    /**
     * A module's pair a position stores is not held until the person belongs
     * to a department, so "right now" leaves it out.
     */
    public function testAStoredPairTheVotersRefuseIsNotListed(): void
    {
        $amani = $this->person('Amani', TierEnum::Staff, ['directory.read', 'notes.read'], 'Housekeeper');
        $this->signedInAs($this->person('Baraka', TierEnum::Admin));

        self::assertSame(['Directory: Read'], $this->ledger($this->browser->request('GET', $this->address($amani))));
    }

    public function testATierHoldsEverythingAndTheRecordSaysSo(): void
    {
        $admin = $this->person('Halima', TierEnum::Admin);
        $this->signedInAs($this->person('Baraka', TierEnum::Admin));

        $page = $this->browser->request('GET', $this->address($admin));

        self::assertStringContainsString('Everything, by tier', $page->filter('[data-grants]')->text());
    }

    /**
     * Showing "everything, by tier" would name the tier, so what somebody may
     * do is shown only where their tier may be seen, and on one's own record.
     */
    public function testWhatSomebodyMayDoIsShownOnlyWhereTheirTierMayBeSeen(): void
    {
        $neema = $this->person('Neema', TierEnum::SuperAdmin);
        $elia = $this->person('Elia', TierEnum::Staff, ['directory.read'], 'Clerk');
        $amani = $this->person('Amani', TierEnum::Staff, ['directory.read'], 'Housekeeper');

        $this->signedInAs($this->person('Baraka', TierEnum::Admin));
        self::assertCount(0, $this->browser->request('GET', $this->address($neema))->filter('[data-grants]'));

        $this->signedInAs($elia);
        self::assertCount(0, $this->browser->request('GET', $this->address($amani))->filter('[data-grants]'));
        self::assertSame(['Directory: Read'], $this->ledger($this->browser->request('GET', $this->address($elia))), 'one\'s own');
    }

    /** The list opens each record, and only now that there is a record to open. */
    public function testTheTeamListOpensEachRecord(): void
    {
        $amani = $this->person('Amani', TierEnum::Staff);
        $this->signedInAs($this->person('Neema', TierEnum::SuperAdmin));

        $page = $this->browser->request('GET', '/team');

        self::assertSame($this->address($amani), $page->filter('tr[data-person="Amani Kimaro"]')->selectLink('Open')->attr('href'));
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

    private function signedInAs(User $user): void
    {
        $this->browser->restart();
        $this->browser->loginUser($user);
    }

    private function address(User $user): string
    {
        return '/team/'.$user->getUuid();
    }

    /**
     * @param list<User> $subjects
     *
     * @return list<bool>
     */
    private function seesTiers(User $viewer, array $subjects): array
    {
        $security = static::getContainer()->get(Kernel::SECURITY);
        self::assertInstanceOf(Security::class, $security);

        return array_map(static fn (User $subject): bool => $security->isGrantedForUser($viewer, AccountVoter::SEE_TIER, $subject), $subjects);
    }

    /**
     * @return list<string> "Concern: Verb, Verb", one a concern held
     */
    private function ledger(Crawler $page): array
    {
        return $page->filter('[data-grants] [data-concern]')->each(static fn (Crawler $row): string => trim((string) preg_replace('/\s+/', ' ', $row->filter('[data-label]')->text())).': '.implode(', ', $row->filter('[data-verb]')->each(static fn (Crawler $verb): string => trim($verb->text()))));
    }
}
