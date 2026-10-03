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
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Vivutio\Bundle\IdentityBundle\Entity\User;
use Vivutio\Bundle\IdentityBundle\Enum\TierEnum;
use Vivutio\Bundle\IdentityBundle\Service\MailAvailability;

/**
 * A forgotten password, ported from uhifadhi's settled pages (auth/forgot and
 * auth/reset): a link good for one hour and used once, the same answer for an
 * address with an account and one without, and an honest page where the
 * installation cannot send mail.
 */
final class ResettingAPasswordTest extends MigrationsTestCase
{
    private const string OLD = 'the old passphrase, long enough';

    private const string NEW = 'a brand new passphrase';

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

    public function testTheSignInCardOffersHelp(): void
    {
        $page = $this->browser->request('GET', '/login');

        self::assertSame('/login/forgot', $page->selectLink('Forgot your password?')->attr('href'));
    }

    public function testAKnownAddressIsSentALink(): void
    {
        $this->person('neema@vivutio-camps.example');

        $this->ask('Neema@Vivutio-Camps.example');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'If that address has an account, a reset link is on its way');
        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertSame('neema@vivutio-camps.example', $email->getTo()[0]->getAddress());
        self::assertMatchesRegularExpression('#http://localhost/login/reset/[A-Za-z0-9]+/[A-Za-z0-9_-]+#', (string) $email->getTextBody());
    }

    /** The same words, and no mail, for an address nobody has: the page tells a stranger nothing. */
    public function testAnUnknownAddressIsAnsweredTheSameAndSentNothing(): void
    {
        $this->ask('nobody@vivutio-camps.example');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'If that address has an account, a reset link is on its way');
        self::assertEmailCount(0);
    }

    public function testADeactivatedAccountIsSentNothing(): void
    {
        $this->person('gone@vivutio-camps.example', active: false);

        $this->ask('gone@vivutio-camps.example');

        self::assertEmailCount(0);
    }

    public function testTheLinkSetsANewPasswordAndSignsIn(): void
    {
        $neema = $this->person('neema@vivutio-camps.example');
        $link = $this->linkFor('neema@vivutio-camps.example');

        $this->browser->request('GET', $link);
        self::assertResponseRedirects('/login/reset', null, 'the token leaves the address bar');
        $page = $this->browser->followRedirect();
        self::assertSame('neema@vivutio-camps.example', trim($page->filter('[data-for]')->text()), 'who the link is for comes from the token');

        $this->browser->submit($page->selectButton('Set the password and sign in')->form(['password' => self::NEW, 'repeat' => self::NEW]));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Your password is set.');
        self::assertTrue($this->hasher()->isPasswordValid($this->reloaded($neema), self::NEW));
        $this->browser->request('GET', '/me');
        self::assertResponseIsSuccessful('signed in');
    }

    public function testALinkWorksOnce(): void
    {
        $this->person('neema@vivutio-camps.example');
        $link = $this->linkFor('neema@vivutio-camps.example');

        $this->setWith($link, self::NEW);
        $this->browser->restart();
        $this->browser->request('GET', $link);
        $page = $this->browser->followRedirect();

        self::assertSelectorTextContains('main', 'That link has expired.');
        self::assertCount(0, $page->selectButton('Set the password and sign in'));
    }

    public function testALinkLastsAnHour(): void
    {
        $this->person('neema@vivutio-camps.example');
        $link = $this->linkFor('neema@vivutio-camps.example');
        $this->connection->executeStatement('UPDATE identity_account_link SET expires_at = ?', [(new \DateTimeImmutable('-1 minute'))->format('Y-m-d H:i:s')]);

        $this->browser->request('GET', $link);
        $this->browser->followRedirect();

        self::assertSelectorTextContains('main', 'That link has expired.');
    }

    /** Asking again replaces the link, so an old email stops working the moment a new one is sent. */
    public function testAskingAgainReplacesTheLink(): void
    {
        $this->person('neema@vivutio-camps.example');
        $first = $this->linkFor('neema@vivutio-camps.example');
        $this->connection->executeStatement('UPDATE identity_account_link SET created_at = ?', [(new \DateTimeImmutable('-5 minutes'))->format('Y-m-d H:i:s')]);
        $this->linkFor('neema@vivutio-camps.example');

        $this->browser->request('GET', $first);
        $this->browser->followRedirect();

        self::assertSelectorTextContains('main', 'That link has expired.');
    }

    /** A verifier that does not match is no link, whatever the selector. */
    public function testATamperedLinkIsNoLink(): void
    {
        $this->person('neema@vivutio-camps.example');
        $link = $this->linkFor('neema@vivutio-camps.example');

        $this->browser->request('GET', substr($link, 0, -4).'AAAA');
        $this->browser->followRedirect();

        self::assertSelectorTextContains('main', 'That link has expired.');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function passwordsThatAreRefused(): iterable
    {
        yield 'too short' => ['short one', 'at least 12 characters'];
        yield 'the person\'s name' => ['neema mollel forever', 'your name'];
        yield 'the person\'s address' => ['reception desk password', 'your email'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('passwordsThatAreRefused')]
    public function testAPasswordThatBreaksARuleIsRefusedAndTheLinkStillWorks(string $password, string $rule): void
    {
        $neema = $this->person('reception@vivutio-camps.example');
        $link = $this->linkFor('reception@vivutio-camps.example');

        $page = $this->setWith($link, $password);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString($rule, $page->filter('.field.wrong .hint')->text());
        self::assertTrue($this->hasher()->isPasswordValid($this->reloaded($neema), self::OLD));
        self::assertCount(1, $page->selectButton('Set the password and sign in'), 'the link still works');
    }

    public function testTheTwoMustMatch(): void
    {
        $this->person('neema@vivutio-camps.example');

        $page = $this->setWith($this->linkFor('neema@vivutio-camps.example'), self::NEW, 'something else entirely');

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('do not match', $page->filter('.field.wrong .hint')->text());
    }

    /** Setting it ends every other session of the account: the point of a reset. */
    public function testSettingItSignsEveryOtherSessionOut(): void
    {
        $neema = $this->person('neema@vivutio-camps.example');
        $elsewhere = static::getContainer()->get('test.client');
        self::assertInstanceOf(KernelBrowser::class, $elsewhere);
        $link = $this->linkFor('neema@vivutio-camps.example');
        $elsewhere->loginUser($this->reloaded($neema));

        $this->setWith($link, self::NEW);

        $elsewhere->request('GET', '/me');
        self::assertTrue($elsewhere->getResponse()->isRedirect('http://localhost/login'));
    }

    /** Without a mail transport the page says so, instead of promising an email that will never come. */
    public function testWithoutMailThePageSaysSo(): void
    {
        $this->person('neema@vivutio-camps.example');
        static::getContainer()->set('identity.mail_availability', new MailAvailability(false));

        $page = $this->browser->request('GET', '/login/forgot');

        self::assertSelectorTextContains('main', 'This installation cannot send email yet.');
        self::assertCount(0, $page->selectButton('Send the link'));
    }

    private function ask(string $email): void
    {
        $page = $this->browser->request('GET', '/login/forgot');
        $this->browser->submit($page->selectButton('Send the link')->form(['email' => $email]));
    }

    private function linkFor(string $email): string
    {
        $this->ask($email);
        $message = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $message);
        $found = [];
        preg_match('#http://localhost(/login/reset/[A-Za-z0-9]+/[A-Za-z0-9_-]+)#', (string) $message->getTextBody(), $found);
        $link = $found[1] ?? null;
        self::assertIsString($link, 'the email carries a link');

        $this->browser->restart();

        return $link;
    }

    private function setWith(string $link, string $password, ?string $repeat = null): Crawler
    {
        $this->browser->request('GET', $link);
        $page = $this->browser->followRedirect();

        return $this->browser->submit($page->selectButton('Set the password and sign in')->form(['password' => $password, 'repeat' => $repeat ?? $password]));
    }

    private function person(string $email, bool $active = true): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setFirstName('Neema')
            ->setLastName('Mollel')
            ->setTier(TierEnum::Staff)
            ->setActive($active);
        $user->setPassword($this->hasher()->hashPassword($user, self::OLD));

        $em = $this->em();
        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function reloaded(User $user): User
    {
        $this->em()->clear();
        $fresh = $this->em()->find(User::class, $user->getId());
        self::assertInstanceOf(User::class, $fresh);

        return $fresh;
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
