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

use PHPUnit\Framework\TestCase;

/**
 * The core is one repository and one version, made of packages that could each
 * stand alone: the contracts and one package per bundle, each with a manifest
 * of its own. The root manifest replaces every one of them at its own version,
 * so requiring the core is requiring them all, and nothing is replaced that is
 * not here.
 */
final class EveryPackageIsDeclaredTest extends TestCase
{
    public function testTheRootReplacesExactlyThePackagesInTheRepository(): void
    {
        $replaced = self::manifest(self::root().'/composer.json')['replace'] ?? [];
        self::assertIsArray($replaced);

        $present = [];
        foreach (self::packageManifests() as $path) {
            $name = self::manifest($path)['name'] ?? null;
            self::assertIsString($name, $path.' names no package');
            $present[$name] = 'self.version';
        }

        ksort($replaced);
        ksort($present);

        self::assertSame($present, $replaced);
    }

    public function testEveryBundleCarriesTheCoresLicence(): void
    {
        $licence = self::manifest(self::root().'/composer.json')['license'] ?? null;

        foreach (self::bundleManifests() as $path) {
            self::assertSame($licence, self::manifest($path)['license'] ?? null, $path);
        }
    }

    /**
     * A module declares itself by implementing the contracts, so their licence
     * decides what licence a module may carry. They are MIT, and ship the text.
     */
    public function testTheContractsAreMitAndShipTheirLicence(): void
    {
        self::assertSame('MIT', self::manifest(self::contractsDir().'/composer.json')['license'] ?? null);

        self::assertFileExists(self::contractsDir().'/LICENSE');
        self::assertStringStartsWith('MIT License', (string) file_get_contents(self::contractsDir().'/LICENSE'));
    }

    public function testEveryBundleIsInstalledAsABundle(): void
    {
        foreach (self::packageManifests() as $path) {
            $expected = str_contains($path, '/Bundle/') ? 'symfony-bundle' : 'library';

            self::assertSame($expected, self::manifest($path)['type'] ?? null, $path);
        }
    }

    /**
     * @return list<string>
     */
    private static function packageManifests(): array
    {
        return [self::contractsDir().'/composer.json', ...self::bundleManifests()];
    }

    /**
     * @return list<string>
     */
    private static function bundleManifests(): array
    {
        $bundles = glob(self::root().'/src/Vivutio/Bundle/*/composer.json');
        self::assertIsArray($bundles);
        self::assertNotEmpty($bundles, 'the core holds no bundle');

        return $bundles;
    }

    private static function contractsDir(): string
    {
        return self::root().'/src/Vivutio/Contracts';
    }

    /**
     * @return array<mixed>
     */
    private static function manifest(string $path): array
    {
        self::assertFileExists($path);

        $manifest = json_decode((string) file_get_contents($path), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($manifest);

        return $manifest;
    }

    private static function root(): string
    {
        return \dirname(__DIR__, 2);
    }
}
