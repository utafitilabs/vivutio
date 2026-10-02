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

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * A module knows the core; the core never knows a module.
 *
 * What an installation can do arrives as modules, each a package of its own
 * that declares itself to the core through the contracts. The core is what
 * every installation shares, so it cannot depend on something only some of
 * them install, and it cannot be written around one module's needs.
 *
 * Held structurally, so the core names no module even to refuse it: the only
 * namespaces under the product's root that this repository may refer to are
 * its own, and no package here requires a module or a pack.
 */
#[CoversNothing]
final class TheCoreKnowsNoModuleTest extends TestCase
{
    /** The namespaces this repository itself defines under the product's root. */
    private const array OWN_NAMESPACES = ['Bundle', 'Contracts', 'Core'];

    public function testNoFileRefersToANamespaceOutsideTheCore(): void
    {
        $root = \dirname(__DIR__, 2);
        $pattern = '/\bVivutio\\\\{1,2}(?!'.implode('\b|', self::OWN_NAMESPACES).'\b)[A-Z][A-Za-z0-9]*/';

        $offenders = [];
        foreach ([...self::phpFilesUnder($root.'/src'), ...self::phpFilesUnder($root.'/tests')] as $file) {
            if (__FILE__ === $file) {
                continue;
            }

            if (1 === preg_match($pattern, (string) file_get_contents($file), $found)) {
                $offenders[substr($file, \strlen($root) + 1)] = $found[0];
            }
        }

        self::assertSame([], $offenders, 'these files name a namespace the core does not define; the core reaches a module only through the contracts');
    }

    public function testNoPackageRequiresAModuleOrAPack(): void
    {
        $root = \dirname(__DIR__, 2);

        $manifests = glob($root.'/src/Vivutio/{Contracts,Bundle/*}/composer.json', \GLOB_BRACE);
        self::assertIsArray($manifests);
        self::assertNotEmpty($manifests);

        $offenders = [];
        foreach ([$root.'/composer.json', ...$manifests] as $path) {
            $manifest = json_decode((string) file_get_contents($path), true, flags: \JSON_THROW_ON_ERROR);
            self::assertIsArray($manifest);

            foreach (['require', 'require-dev', 'suggest', 'replace'] as $section) {
                $named = $manifest[$section] ?? [];
                self::assertIsArray($named);

                foreach (array_keys($named) as $package) {
                    if (1 === preg_match('/-(module|pack)$/', (string) $package)) {
                        $offenders[] = substr($path, \strlen($root) + 1).': '.$package;
                    }
                }
            }
        }

        self::assertSame([], $offenders, 'the core depends on nothing an installation may not have');
    }

    /**
     * @return list<string>
     */
    private static function phpFilesUnder(string $directory): array
    {
        $found = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if (!$file instanceof \SplFileInfo || 'php' !== $file->getExtension()) {
                continue;
            }

            if (str_contains($file->getPathname(), '/var/')) {
                continue;
            }

            $found[] = $file->getPathname();
        }

        sort($found);

        return $found;
    }
}
