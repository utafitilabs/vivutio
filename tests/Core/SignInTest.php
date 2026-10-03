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
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Vivutio\Bundle\IdentityBundle\Entity\User;
use Vivutio\Bundle\IdentityBundle\Enum\TierEnum;

/**
 * Signing in and out, driven over HTTP through the firewall an installation
 * writes, against an empty database migrated as an installation migrates it.
 */
final class SignInTest extends MigrationsTestCase
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

    public function testAStrangerIsShownTheSignInForm(): void
    {
        $page = $this->browser->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $page->filter('form input[name="_username"][type="email"]'));
        self::assertCount(1, $page->filter('form input[name="_password"][type="password"]'));
        self::assertCount(1, $page->filter('form input[name="_csrf_token"]'));
        self::assertCount(1, $page->filter('form input[name="_remember_me"][type="checkbox"]'));
    }

    /**
     * The card as designed in vivutio-designs/auth/sign-in.html: one card on
     * the ground with no bar and no menu, vivutio's mark and no organization,
     * the two fields, the box to tick and the full-width button.
     */
    public function testTheCardIsTheDesignedOne(): void
    {
        $page = $this->browser->request('GET', '/login');

        self::assertCount(1, $page->filter('link[rel="stylesheet"][href="/bundles/shell/vivutio.css"]'));
        self::assertCount(1, $page->filter('script[src="/bundles/shell/vivutio.js"]'));
        self::assertCount(1, $page->filter('main.single > section.panel.single-card > form[method="post"]'));
        self::assertCount(0, $page->filter('.bar, .menu'), 'a stranger sees no bar and no menu');
        self::assertSame('vivutio', trim($page->filter('.single-brand')->text()));
        self::assertCount(1, $page->filter('.single-brand svg'));
        self::assertCount(2, $page->filter('.field > label + input.input'));
        self::assertSame('Remember me for a week', trim($page->filter('label.check')->text()));
        self::assertSame('Sign in', trim($page->filter('button.button.main.wide[type="submit"]')->text()));
        self::assertCount(0, $page->filter('[role="alert"]'), 'no refusal before anybody has tried');
    }

    public function testEachRefusalCarriesItsOwnIcon(): void
    {
        $this->person('gone@vivutio-camps.example', active: false);

        $this->signIn('nobody@vivutio-camps.example', self::PASSWORD);
        $page = $this->browser->followRedirect();
        self::assertCount(1, $page->filter('.notice.danger[role="alert"] svg.icon-circle-alert'));

        $this->signIn('gone@vivutio-camps.example', self::PASSWORD);
        $page = $this->browser->followRedirect();
        self::assertCount(1, $page->filter('.notice.danger[role="alert"] svg.icon-user-x'));
    }

    public function testAnyOtherPageSendsAStrangerToSignIn(): void
    {
        $this->browser->request('GET', '/');

        self::assertResponseRedirects('http://localhost/login');
    }

    public function testTheRightCredentialsSignSomebodyIn(): void
    {
        $this->person('neema.mollel@vivutio-camps.example');

        $this->signIn('neema.mollel@vivutio-camps.example', self::PASSWORD);

        self::assertResponseRedirects('/');
        $this->browser->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'neema.mollel@vivutio-camps.example');
    }

    /** An address is one person however it is typed: it is stored in lowercase. */
    public function testTheAddressIsReadWithoutRegardToCase(): void
    {
        $this->person('neema.mollel@vivutio-camps.example');

        $this->signIn('Neema.Mollel@Vivutio-Camps.example', self::PASSWORD);

        self::assertResponseRedirects('/');
    }

    public function testAWrongPasswordIsRefusedAndSaidSo(): void
    {
        $this->person('neema.mollel@vivutio-camps.example');

        $this->signIn('neema.mollel@vivutio-camps.example', 'not the passphrase');

        self::assertResponseRedirects('/login');
        $this->browser->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'Invalid credentials.');
        self::assertInputValueSame('_username', 'neema.mollel@vivutio-camps.example');
    }

    /**
     * An address nobody has is refused in the same words as a wrong
     * password, so the page tells a stranger nothing about who works here.
     */
    public function testAnUnknownAddressIsRefusedInTheSameWordsAsAWrongPassword(): void
    {
        $this->signIn('nobody@vivutio-camps.example', self::PASSWORD);

        $this->browser->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'Invalid credentials.');
    }

    public function testADeactivatedAccountIsRefusedWithTheReason(): void
    {
        $this->person('gone@vivutio-camps.example', active: false);

        $this->signIn('gone@vivutio-camps.example', self::PASSWORD);

        $this->browser->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'deactivated');
        $this->browser->request('GET', '/');
        self::assertResponseRedirects('http://localhost/login');
    }

    public function testAFormWithoutItsTokenSignsNobodyIn(): void
    {
        $this->person('neema.mollel@vivutio-camps.example');

        $this->browser->request('POST', '/login', [
            '_username' => 'neema.mollel@vivutio-camps.example',
            '_password' => self::PASSWORD,
        ]);

        $this->browser->request('GET', '/');
        self::assertResponseRedirects('http://localhost/login');
    }

    public function testSomebodySignedInIsNotShownTheFormAgain(): void
    {
        $this->person('neema.mollel@vivutio-camps.example');
        $this->signIn('neema.mollel@vivutio-camps.example', self::PASSWORD);

        $this->browser->request('GET', '/login');

        self::assertResponseRedirects('/');
    }

    public function testSigningOutEndsTheSession(): void
    {
        $this->person('neema.mollel@vivutio-camps.example');
        $this->signIn('neema.mollel@vivutio-camps.example', self::PASSWORD);

        $this->browser->request('GET', '/logout');
        self::assertResponseRedirects('http://localhost/login');

        $this->browser->request('GET', '/');
        self::assertResponseRedirects('http://localhost/login');
    }

    /**
     * Deactivating somebody takes effect on their next click, not when their
     * session would have expired.
     */
    public function testDeactivatingAnAccountEndsTheSessionItAlreadyHas(): void
    {
        $neema = $this->person('neema.mollel@vivutio-camps.example');
        $this->signIn('neema.mollel@vivutio-camps.example', self::PASSWORD);

        $this->connection->executeStatement('UPDATE identity_user SET active = false WHERE id = ?', [$neema->getId()]);

        $this->browser->request('GET', '/');
        self::assertResponseRedirects('http://localhost/login');
    }

    /** A demoted Admin does not carry the old tier's standing to the end of the session. */
    public function testChangingTheTierEndsTheSessionItAlreadyHas(): void
    {
        $neema = $this->person('neema.mollel@vivutio-camps.example');
        $this->connection->executeStatement("UPDATE identity_user SET tier = 'admin' WHERE id = ?", [$neema->getId()]);
        $this->signIn('neema.mollel@vivutio-camps.example', self::PASSWORD);

        $this->connection->executeStatement("UPDATE identity_user SET tier = 'staff' WHERE id = ?", [$neema->getId()]);

        $this->browser->request('GET', '/');
        self::assertResponseRedirects('http://localhost/login');
    }

    /** A password changed elsewhere ends every other session with the old one. */
    public function testChangingThePasswordEndsTheSessionItAlreadyHas(): void
    {
        $neema = $this->person('neema.mollel@vivutio-camps.example');
        $this->signIn('neema.mollel@vivutio-camps.example', self::PASSWORD);

        $this->connection->executeStatement("UPDATE identity_user SET password = 'another hash' WHERE id = ?", [$neema->getId()]);

        $this->browser->request('GET', '/');
        self::assertResponseRedirects('http://localhost/login');
    }

    public function testAnUnchangedAccountKeepsItsSession(): void
    {
        $this->person('neema.mollel@vivutio-camps.example');
        $this->signIn('neema.mollel@vivutio-camps.example', self::PASSWORD);

        $this->browser->request('GET', '/');
        self::assertResponseIsSuccessful();
        $this->browser->request('GET', '/');
        self::assertResponseIsSuccessful();
    }

    public function testRememberMeKeepsSomebodySignedInForAWeek(): void
    {
        $this->person('neema.mollel@vivutio-camps.example');

        $this->signIn('neema.mollel@vivutio-camps.example', self::PASSWORD, remember: true);

        $cookie = $this->browser->getCookieJar()->get('REMEMBERME');
        self::assertNotNull($cookie, 'ticking the box sets the cookie');
        self::assertEqualsWithDelta(time() + 604800, (int) $cookie->getExpiresTime(), 60);
    }

    public function testWithoutTheBoxTickedNothingIsRemembered(): void
    {
        $this->person('neema.mollel@vivutio-camps.example');

        $this->signIn('neema.mollel@vivutio-camps.example', self::PASSWORD);

        self::assertNull($this->browser->getCookieJar()->get('REMEMBERME'));
    }

    private function signIn(string $email, string $password, bool $remember = false): void
    {
        $page = $this->browser->request('GET', '/login');
        $form = $page->selectButton('Sign in')->form([
            '_username' => $email,
            '_password' => $password,
        ]);

        if ($remember) {
            $box = $form['_remember_me'];
            self::assertInstanceOf(ChoiceFormField::class, $box);
            $box->tick();
        }

        $this->browser->submit($form);
    }

    private function person(string $email, bool $active = true): User
    {
        $hasher = static::getContainer()->get('test_public.hasher');
        self::assertInstanceOf(UserPasswordHasherInterface::class, $hasher);

        $user = (new User())
            ->setEmail($email)
            ->setFirstName('Neema')
            ->setLastName('Mollel')
            ->setTier(TierEnum::Staff)
            ->setActive($active);
        $user->setPassword($hasher->hashPassword($user, self::PASSWORD));

        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $em->persist($user);
        $em->flush();

        return $user;
    }
}
