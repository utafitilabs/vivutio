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
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Vivutio\Bundle\IdentityBundle\Entity\Position;
use Vivutio\Bundle\IdentityBundle\Entity\User;
use Vivutio\Bundle\IdentityBundle\Enum\TierEnum;
use Vivutio\Bundle\IdentityBundle\Service\MailAvailability;

/**
 * Adding somebody, ported from uhifadhi's team/invite and auth/accept: create
 * them with a first password handed over in the room, or invite them by email
 * to name themselves and choose their own. Adding people is managing the
 * directory, the tiers' alone (uhifadhi #67).
 */
final class AddingSomebodyTest extends MigrationsTestCase
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

    public function testTheTeamListLeadsToAddingSomebody(): void
    {
        $this->signedInAs($this->person('baraka@vivutio-camps.example', TierEnum::Admin));

        $page = $this->browser->request('GET', '/team');

        self::assertSame('/team/add', $page->selectLink('Add somebody')->attr('href'));
    }

    public function testStaffAddNobody(): void
    {
        $elia = $this->person('elia@vivutio-camps.example', TierEnum::Staff);
        $everything = (new Position())->setName('Everything')->setGrants(['directory.read', 'personal_details.read', 'positions.read', 'dashboard.read']);
        $this->em()->persist($everything);
        $elia->setPosition($everything);
        $this->em()->flush();
        $this->signedInAs($elia);

        self::assertCount(0, $this->browser->request('GET', '/team')->selectLink('Add somebody'));
        $this->browser->request('GET', '/team/add');
        self::assertResponseStatusCodeSame(403);
        $this->browser->request('POST', '/team/add/invite', ['email' => 'taken.over@vivutio-camps.example']);
        self::assertResponseStatusCodeSame(403);
    }

    public function testCreatedWithAPasswordTheyMaySignInAtOnce(): void
    {
        $guide = (new Position())->setName('Guide')->setGrants(['directory.read']);
        $this->em()->persist($guide);
        $this->em()->flush();
        $this->signedInAs($this->person('baraka@vivutio-camps.example', TierEnum::Admin));

        $page = $this->browser->request('GET', '/team/add');
        $this->browser->submit($page->selectButton('Create')->form([
            'first_name' => 'Amani',
            'last_name' => 'Lyimo',
            'email' => 'Amani.Lyimo@vivutio-camps.example',
            'password' => self::PASSWORD,
            'position' => (string) $guide->getUuid(),
        ]));

        $amani = $this->find('amani.lyimo@vivutio-camps.example');
        self::assertResponseRedirects('/team/'.$amani->getUuid());
        self::assertSame(['Amani', 'Lyimo', 'Guide', TierEnum::Staff], [$amani->getFirstName(), $amani->getLastName(), $amani->getPosition()?->getName(), $amani->getTier()]);
        self::assertTrue($amani->isVerified());
        self::assertTrue($this->hasher()->isPasswordValid($amani, self::PASSWORD));
        self::assertEmailCount(0);
    }

    public function testAnAddressInUseIsRefused(): void
    {
        $this->person('amani@vivutio-camps.example', TierEnum::Staff);
        $this->signedInAs($this->person('baraka@vivutio-camps.example', TierEnum::Admin));

        $page = $this->browser->request('GET', '/team/add');
        $page = $this->browser->submit($page->selectButton('Send the invitation')->form(['email' => 'Amani@vivutio-camps.example']));

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('already', $page->filter('.field.wrong .hint')->text());
    }

    public function testAnInvitationIsMailedAndTheAccountWaitsInvited(): void
    {
        $this->signedInAs($this->person('baraka@vivutio-camps.example', TierEnum::Admin));

        $this->invite('halima@vivutio-camps.example');

        $halima = $this->find('halima@vivutio-camps.example');
        self::assertResponseRedirects('/team/'.$halima->getUuid());
        self::assertFalse($halima->isVerified());
        self::assertSame('Baraka Kimaro', $halima->getInvitedBy()?->getFullName());
        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertStringContainsString('Baraka Kimaro added you', (string) $email->getTextBody());

        $list = $this->browser->request('GET', '/team');
        self::assertSame('Invited', trim($list->filter('tr[data-person] [data-account-state="invited"]')->text()));
    }

    public function testAcceptingNamesThemselvesSetsThePasswordAndSignsIn(): void
    {
        $this->signedInAs($this->person('baraka@vivutio-camps.example', TierEnum::Admin));
        $link = $this->invite('halima@vivutio-camps.example');

        $this->browser->restart();
        $this->browser->request('GET', $link);
        self::assertResponseRedirects('/login/join');
        $page = $this->browser->followRedirect();
        self::assertStringContainsString('invited by Baraka Kimaro', $page->filter('main')->text());
        self::assertSame('halima@vivutio-camps.example', trim($page->filter('[data-for]')->text()));

        $this->browser->submit($page->selectButton('Set it and sign in')->form([
            'first_name' => 'Halima',
            'last_name' => 'Saidi',
            'password' => self::PASSWORD,
            'repeat' => self::PASSWORD,
        ]));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Welcome, Halima.');
        $halima = $this->find('halima@vivutio-camps.example');
        self::assertTrue($halima->isVerified());
        self::assertSame('Halima Saidi', $halima->getFullName());
        self::assertTrue($this->hasher()->isPasswordValid($halima, self::PASSWORD));

        $this->browser->restart();
        $this->browser->request('GET', $link);
        $this->browser->followRedirect();
        self::assertSelectorTextContains('main', 'That invitation is no longer open.');
    }

    /** Until they accept, an invited account cannot sign in, and asking for a reset link sends nothing: the invitation is the way in. */
    public function testAnInvitedAccountIsReachedOnlyThroughItsInvitation(): void
    {
        $this->signedInAs($this->person('baraka@vivutio-camps.example', TierEnum::Admin));
        $this->invite('halima@vivutio-camps.example');
        $this->browser->restart();

        $page = $this->browser->request('GET', '/login/forgot');
        $this->browser->submit($page->selectButton('Send the link')->form(['email' => 'halima@vivutio-camps.example']));

        self::assertEmailCount(0);
    }

    /** An invited account's card sends its invitation again, never a reset, which would skip naming themselves. */
    public function testAnInvitationIsSentAgainFromTheConfigurePage(): void
    {
        $this->signedInAs($this->person('baraka@vivutio-camps.example', TierEnum::Admin));
        $first = $this->invite('halima@vivutio-camps.example');
        $halima = $this->find('halima@vivutio-camps.example');

        $page = $this->browser->request('GET', '/team/'.$halima->getUuid().'/configure');
        self::assertCount(0, $page->filter('form[action$="/send-reset"]'), 'no reset for somebody invited');
        $this->browser->submit($page->selectButton('Send again')->form());

        self::assertResponseRedirects('/team/'.$halima->getUuid().'/configure');
        self::assertEmailCount(1);
        $this->browser->restart();
        $this->browser->request('GET', $first);
        $this->browser->followRedirect();
        self::assertSelectorTextContains('main', 'That invitation is no longer open.', 'the new link replaces the old');
    }

    public function testWithoutMailInvitingIsRefusedNotHidden(): void
    {
        $this->signedInAs($this->person('baraka@vivutio-camps.example', TierEnum::Admin));
        static::getContainer()->set('identity.mail_availability', new MailAvailability(false));

        $page = $this->browser->request('GET', '/team/add');

        self::assertCount(0, $page->selectButton('Send the invitation'));
        self::assertStringContainsString('cannot send email yet', $page->filter('[data-invite]')->text());
        self::assertCount(1, $page->selectButton('Create'), 'creating with a password needs no mail');
    }

    private function invite(string $email): string
    {
        $page = $this->browser->request('GET', '/team/add');
        $this->browser->submit($page->selectButton('Send the invitation')->form(['email' => $email]));

        $message = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $message);
        $found = [];
        preg_match('#http://localhost(/login/join/[A-Za-z0-9]+/[A-Za-z0-9_-]+)#', (string) $message->getTextBody(), $found);
        $link = $found[1] ?? null;
        self::assertIsString($link, 'the email carries a link');

        return $link;
    }

    private function person(string $email, TierEnum $tier): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setFirstName(ucfirst(explode('@', $email)[0]))
            ->setLastName('Kimaro')
            ->setTier($tier)
            ->setPassword('a hash, never a password');
        $this->em()->persist($user);
        $this->em()->flush();

        return $user;
    }

    private function find(string $email): User
    {
        $this->em()->clear();
        $user = $this->em()->getRepository(User::class)->findOneBy(['email' => $email]);
        self::assertInstanceOf(User::class, $user);

        return $user;
    }

    private function signedInAs(User $user): void
    {
        $this->browser->restart();
        $this->browser->loginUser($user);
    }

    private function hasher(): UserPasswordHasherInterface
    {
        $hasher = static::getContainer()->get('test_public.hasher');
        self::assertInstanceOf(UserPasswordHasherInterface::class, $hasher);

        return $hasher;
    }

    private function em(): EntityManagerInterface
    {
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $em);

        return $em;
    }
}
