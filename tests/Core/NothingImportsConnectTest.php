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
 * An instance is complete without the hub. The connection to it ships in the
 * core, switched off, and that only holds while nothing else depends on it:
 * every other package reaches a partner through the channel contract, and the
 * Connect bundle is one implementation of that contract.
 *
 * So no file outside the Connect bundle names its namespace, and no package
 * outside it requires its package, in the contracts and in every other bundle
 * alike.
 */
final class NothingImportsConnectTest extends TestCase
{
    private const string CONNECT_NAMESPACE = 'Vivutio\\Bundle\\ConnectBundle';

    private const string CONNECT_PACKAGE = 'vivutio/connect-bundle';

    public function testNoPackageOutsideConnectNamesItsNamespace(): void
    {
        $source = \dirname(__DIR__, 2).'/src/Vivutio';
        $connect = $source.'/Bundle/ConnectBundle/';

        $offenders = [];
        foreach (self::phpFilesUnder($source) as $file) {
            if (str_starts_with($file, $connect)) {
                continue;
            }

            if (str_contains((string) file_get_contents($file), self::CONNECT_NAMESPACE)) {
                $offenders[] = substr($file, \strlen($source) + 1);
            }
        }

        self::assertSame([], $offenders, 'these files depend on the hub connection; they must use the channel contract instead');
    }

    public function testNoPackageOutsideConnectRequiresItsPackage(): void
    {
        $source = \dirname(__DIR__, 2).'/src/Vivutio';

        $manifests = glob($source.'/{Contracts,Bundle/*}/composer.json', \GLOB_BRACE);
        self::assertIsArray($manifests);
        self::assertNotEmpty($manifests);

        $offenders = [];
        foreach ($manifests as $path) {
            if (str_starts_with($path, $source.'/Bundle/ConnectBundle/')) {
                continue;
            }

            $manifest = json_decode((string) file_get_contents($path), true, flags: \JSON_THROW_ON_ERROR);
            self::assertIsArray($manifest);

            foreach (['require', 'require-dev'] as $section) {
                $required = $manifest[$section] ?? [];
                self::assertIsArray($required);

                if (\array_key_exists(self::CONNECT_PACKAGE, $required)) {
                    $offenders[] = substr($path, \strlen($source) + 1);
                }
            }
        }

        self::assertSame([], $offenders, 'these packages require the hub connection; they must depend on the contracts instead');
    }

    /**
     * @return iterable<string>
     */
    private static function phpFilesUnder(string $directory): iterable
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if ($file instanceof \SplFileInfo && 'php' === $file->getExtension()) {
                yield $file->getPathname();
            }
        }
    }
}
