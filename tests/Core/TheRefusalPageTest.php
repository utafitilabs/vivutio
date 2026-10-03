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
use Vivutio\Bundle\IdentityBundle\Entity\User;
use Vivutio\Bundle\IdentityBundle\Enum\TierEnum;

/**
 * A page somebody's position does not reach answers inside the frame, as
 * uhifadhi's does: a 403 that names nothing about what was asked for.
 */
final class TheRefusalPageTest extends MigrationsTestCase
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

    public function testARefusedPageIsTheNotAllowedPageInsideTheFrame(): void
    {
        $this->signedInAsStaffWithoutAPosition();

        $page = $this->browser->request('GET', '/team');

        self::assertResponseStatusCodeSame(403);
        self::assertCount(1, $page->filter('header.bar'), 'inside the frame');
        self::assertSame('vivutio / not allowed', trim((string) preg_replace('/\s+/', ' ', $page->filter('.trail')->text())));
        self::assertSelectorTextContains('.empty b', 'There is nothing here you can open');
        self::assertSelectorTextContains('.empty span', 'The address may be mistyped, or lead somewhere your position does not reach.');
        self::assertSame('javascript:history.back()', $page->selectLink('Back')->attr('href'));
        self::assertSame('/me', $page->selectLink('Your dashboard')->attr('href'));
    }

    /** It says the same thing whatever was asked for, so a refusal confirms nothing exists. */
    public function testItNamesNothingAboutWhatWasAskedFor(): void
    {
        $this->signedInAsStaffWithoutAPosition();

        $page = $this->browser->request('GET', '/team?q=grace');

        self::assertStringNotContainsString('grace', strtolower((string) $page->filter('main')->html()));
        self::assertStringNotContainsString('directory', (string) $page->filter('main')->html());
    }

    public function testAStrangerIsStillSentToSignIn(): void
    {
        $this->browser->request('GET', '/team');

        self::assertResponseRedirects('http://localhost/login');
    }

    private function signedInAsStaffWithoutAPosition(): void
    {
        $user = (new User())
            ->setEmail('clerk@vivutio-camps.example')
            ->setFirstName('Clerk')
            ->setLastName('Kimaro')
            ->setTier(TierEnum::Staff)
            ->setPassword('a hash, never a password');

        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $em->persist($user);
        $em->flush();

        $this->browser->loginUser($user);
    }
}
