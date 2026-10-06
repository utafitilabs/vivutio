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
use Symfony\Component\HttpKernel\Kernel;
use Vivutio\Bundle\IdentityBundle\Entity\User;
use Vivutio\Bundle\IdentityBundle\Enum\TierEnum;

/**
 * Settings › Installation, as drawn (vivutio-designs settings/installation):
 * what this installation runs, read from it as it is now. The core's version
 * and the modules beside it come from Composer, each with its own description;
 * the platform under it, PHP, Symfony, the database and the environment, from
 * the running process. Read with settings.read, by the tiers.
 */
final class TheInstallationTest extends MigrationsTestCase
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

    public function testTheInstallationSaysWhatItRunsAndOnWhat(): void
    {
        $this->signedInAs($this->person('Baraka', TierEnum::Admin));

        $page = $this->browser->request('GET', '/settings/installation');
        self::assertResponseIsSuccessful();
        self::assertSame('Installation', trim($page->filter('nav.tabs [aria-current="page"]')->text()));
        self::assertSame(['Core', 'Modules', 'PHP', 'Symfony'], $page->filter('.band .k')->each(static fn (Crawler $k): string => trim($k->text())));

        $core = $page->filter('tr[data-package="vivutio/vivutio"]');
        self::assertCount(1, $core);
        self::assertStringContainsString('The vivutio core', $core->filter('small')->text());

        $runtime = $page->filter('[data-runtime] .fact')->each(static fn (Crawler $fact): array => [trim($fact->filter('span')->text()), trim($fact->filter('b')->text())]);
        self::assertSame(['PHP', \PHP_VERSION], $runtime[0]);
        self::assertSame(['Symfony', Kernel::VERSION], $runtime[1]);
        self::assertSame('Database', $runtime[2][0]);
        self::assertStringStartsWith('PostgreSQL ', $runtime[2][1]);
        self::assertSame(['Environment', 'test'], $runtime[3]);

        $organization = $this->browser->request('GET', '/settings/organization');
        self::assertSame('/settings/installation', $organization->filter('nav.tabs')->selectLink('Installation')->attr('href'));
    }

    public function testStaffSeeNoInstallation(): void
    {
        $this->signedInAs($this->person('Elia', TierEnum::Staff));

        $this->browser->request('GET', '/settings/installation');
        self::assertResponseStatusCodeSame(403);
    }

    private function person(string $name, TierEnum $tier): User
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

    private function em(): EntityManagerInterface
    {
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $em);

        return $em;
    }
}
