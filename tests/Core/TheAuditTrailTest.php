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
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Vivutio\Bundle\IdentityBundle\Entity\Department;
use Vivutio\Bundle\IdentityBundle\Entity\Organization;
use Vivutio\Bundle\IdentityBundle\Entity\Position;
use Vivutio\Bundle\IdentityBundle\Entity\User;
use Vivutio\Bundle\IdentityBundle\Enum\TierEnum;

/**
 * The audit trail, as ruled (11 October 2026) and drawn (vivutio-designs
 * settings/audit): every change to an account, a position's grants, a
 * department's allowances and head and the organization's settings, and each
 * sign-in, succeeded or failed, with who and from where; read by Super Admins
 * alone, on Settings › Audit log.
 */
final class TheAuditTrailTest extends MigrationsTestCase
{
    private const string PASSWORD = 'a long enough passphrase';

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

    public function testEverySignInIsRecordedWhetherItSucceedsOrFails(): void
    {
        $this->person('neema.mollel@vivutio-camps.example', 'Neema', 'Mollel', TierEnum::SuperAdmin);
        $this->signIn('neema.mollel@vivutio-camps.example', 'not the passphrase');
        $this->signIn('neema.mollel@vivutio-camps.example', self::PASSWORD);

        self::assertSame([
            'Signed in | Neema Mollel · 127.0.0.1',
            'A sign-in failed for neema.mollel@vivutio-camps.example | 127.0.0.1',
            'Added Neema Mollel as Super Admin | The installation',
        ], $this->lines());
    }

    public function testAChangeToWhoMayDoWhatIsRecordedWithWhoMadeIt(): void
    {
        $this->person('neema.mollel@vivutio-camps.example', 'Neema', 'Mollel', TierEnum::SuperAdmin);
        $amani = $this->person('amani.lyimo@vivutio-camps.example', 'Amani', 'Lyimo', TierEnum::Staff);
        $this->signIn('neema.mollel@vivutio-camps.example', self::PASSWORD);

        $page = $this->browser->request('GET', '/team/'.$amani->getUuid().'/configure');
        $form = $page->selectButton('Save sign-in')->form();
        $this->browser->request('POST', (string) $form->getUri(), ['email' => 'amani.lyimo@vivutio-camps.example', 'tier' => 'admin', '_token' => $form->getValues()['_token']]);
        self::assertResponseRedirects();

        self::assertSame('Raised Amani Lyimo from Staff to Admin | Neema Mollel · 127.0.0.1', $this->lines()[0]);
        self::assertSame('Amani Lyimo', $this->browser->request('GET', '/settings/audit')->filter('[data-event] span b')->first()->text());
    }

    public function testPositionsDepartmentsAndTheOrganizationAreRecorded(): void
    {
        $this->person('neema.mollel@vivutio-camps.example', 'Neema', 'Mollel', TierEnum::SuperAdmin);
        $em = $this->em();
        $housekeeper = (new Position())->setName('Housekeeper')->setGrants(['notes.read', 'notes.record']);
        $em->persist($housekeeper);
        $em->flush();
        $housekeeper->setGrants(['notes.read', 'positions.read']);
        $front = (new Department())->setName('Front office')->setAllows(['notes.read']);
        $em->persist($front);
        $em->flush();
        $em->persist((new Organization())->setName('Vivutio Camps'));
        $em->flush();
        $organization = $em->find(Organization::class, Organization::ONLY);
        self::assertInstanceOf(Organization::class, $organization);
        $front->setAllows(['notes.read', 'notes.record'])->setHead($housekeeper);
        $organization->setTimeZone('Africa/Dar_es_Salaam');
        $em->flush();

        $this->signIn('neema.mollel@vivutio-camps.example', self::PASSWORD);
        self::assertSame([
            'Signed in | Neema Mollel · 127.0.0.1',
            'Changed the organization’s time zone from not set to Africa/Dar_es_Salaam | The installation',
            'Made Front office’s head the Housekeeper seat | The installation',
            'Changed what Front office allows: added Notes · record | The installation',
            'Recorded the organization Vivutio Camps | The installation',
            'Added the department Front office | The installation',
            'Changed what Housekeeper grants: added Positions · read; took away Notes · record | The installation',
            'Added the position Housekeeper | The installation',
            'Added Neema Mollel as Super Admin | The installation',
        ], $this->lines());
    }

    public function testTheLogIsFilteredByKindTimeAndWords(): void
    {
        $this->person('neema.mollel@vivutio-camps.example', 'Neema', 'Mollel', TierEnum::SuperAdmin);
        $this->person('amani.lyimo@vivutio-camps.example', 'Amani', 'Lyimo', TierEnum::Staff);
        $this->signIn('neema.mollel@vivutio-camps.example', self::PASSWORD);

        $page = $this->browser->request('GET', '/settings/audit?kind=sign_in');
        self::assertSame(['Signed in'], $page->filter('[data-event] span')->each(fn (Crawler $s): string => $this->text($s, true)));
        $page = $this->browser->request('GET', '/settings/audit?q=amani');
        self::assertSame(['Added Amani Lyimo as Staff'], $page->filter('[data-event] span')->each(fn (Crawler $s): string => $this->text($s, true)));
        $page = $this->browser->request('GET', '/settings/audit?time=today');
        self::assertCount(3, $page->filter('[data-event]'));
        self::assertSame('showing 3 of 3', $this->text($page->filter('.shown')));
    }

    public function testOnlySuperAdminsReadTheLog(): void
    {
        $this->person('baraka.kimaro@vivutio-camps.example', 'Baraka', 'Kimaro', TierEnum::Admin);
        $this->signIn('baraka.kimaro@vivutio-camps.example', self::PASSWORD);
        $this->browser->request('GET', '/settings/audit');
        self::assertResponseStatusCodeSame(403);
        self::assertCount(0, $this->browser->request('GET', '/settings/organization')->filter('nav.tabs a:contains("Audit log")'));
    }

    /**
     * The log's lines as a Super Admin reads them, newest first: what, then
     * who and from where.
     *
     * @return list<string>
     */
    private function lines(): array
    {
        $page = $this->browser->request('GET', '/settings/audit');
        self::assertResponseIsSuccessful();

        return $page->filter('[data-event]')->each(fn (Crawler $line): string => $this->text($line->filter('span'), true).' | '.$this->text($line->filter('b')->last()));
    }

    /** A line's text; its time, "14:22 · ", left off where asked. */
    private function text(Crawler $node, bool $withoutTime = false): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $node->text()));

        return $withoutTime ? (string) preg_replace('/^\d{2}:\d{2} · /u', '', $text) : $text;
    }

    private function signIn(string $email, string $password): void
    {
        $this->browser->restart();
        $page = $this->browser->request('GET', '/login');
        $this->browser->submit($page->selectButton('Sign in')->form(['_username' => $email, '_password' => $password]));
    }

    private function person(string $email, string $first, string $last, TierEnum $tier): User
    {
        $hasher = static::getContainer()->get('test_public.hasher');
        self::assertInstanceOf(UserPasswordHasherInterface::class, $hasher);
        $user = (new User())->setEmail($email)->setFirstName($first)->setLastName($last)->setTier($tier);
        $user->setPassword($hasher->hashPassword($user, self::PASSWORD));
        $this->em()->persist($user);
        $this->em()->flush();

        return $user;
    }

    private function em(): EntityManagerInterface
    {
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $em);

        return $em;
    }
}
