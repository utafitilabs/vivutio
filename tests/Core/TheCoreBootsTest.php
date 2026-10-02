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

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Vivutio\Bundle\ConnectBundle\ConnectBundle;
use Vivutio\Bundle\IdentityBundle\IdentityBundle;
use Vivutio\Bundle\PartnerBundle\PartnerBundle;
use Vivutio\Bundle\PlaceBundle\PlaceBundle;
use Vivutio\Bundle\RegistryBundle\RegistryBundle;
use Vivutio\Bundle\ShellBundle\ShellBundle;
use Vivutio\Core\Tests\Application\Kernel;

/**
 * An installation that registers the core's bundles gets a kernel that boots,
 * and each bundle answers for its own directory.
 *
 * The kernel is named in code, not by KERNEL_CLASS: one repository holds several
 * bundles, and an env var could name only one kernel for all their suites.
 *
 * @see https://symfony.com/doc/current/testing.html — "you can also override
 *      the getKernelClass() or createKernel() methods of your functional test,
 *      which takes precedence over the KERNEL_CLASS env var"
 * @see vendor/symfony/framework-bundle/Test/KernelTestCase.php
 */
final class TheCoreBootsTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    /**
     * @return iterable<string, array{class-string<BundleInterface>}>
     */
    public static function coreBundles(): iterable
    {
        yield 'Registry' => [RegistryBundle::class];
        yield 'Identity' => [IdentityBundle::class];
        yield 'Shell' => [ShellBundle::class];
        yield 'Place' => [PlaceBundle::class];
        yield 'Partner' => [PartnerBundle::class];
        yield 'Connect' => [ConnectBundle::class];
    }

    /**
     * @param class-string<BundleInterface> $class
     */
    #[DataProvider('coreBundles')]
    public function testTheKernelBootsWithTheBundle(string $class): void
    {
        $kernel = self::bootKernel();

        $registered = array_map(static fn (BundleInterface $bundle): string => $bundle::class, $kernel->getBundles());

        self::assertContains($class, $registered);
    }

    /**
     * A bundle class sits at its package root, beside its composer.json, so
     * the package root is the bundle root: templates/, config/ and public/
     * are looked for there and nowhere above it.
     *
     * @param class-string<BundleInterface> $class
     */
    #[DataProvider('coreBundles')]
    public function testTheBundleAnswersForItsOwnDirectory(string $class): void
    {
        $bundle = new $class();

        $classFile = (new \ReflectionClass($class))->getFileName();
        self::assertIsString($classFile);

        self::assertSame(\dirname($classFile), $bundle->getPath());
        self::assertFileExists($bundle->getPath().'/composer.json');
    }
}
