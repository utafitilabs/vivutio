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
 * One spelling, everywhere a reader or a developer meets the word:
 * "organization".
 *
 * Nobody types the other spelling on purpose. It arrives one string at a time
 * and is invisible until two files are read side by side, and a class named one
 * way beside a screen worded the other is a mismatch nothing else reports.
 *
 * Everything the repository ships is swept: source, manifests, templates,
 * translations, configuration and documents. The letters are flagged wherever
 * they appear, not only between word boundaries, so identifiers, route names
 * and service ids carry the same spelling as the copy. Test files are outside
 * the sweep, this one included.
 */
#[CoversNothing]
final class OneSpellingOfOrganizationTest extends TestCase
{
    private const string OTHER_SPELLING = 'organis';

    private const array SWEPT_EXTENSIONS = ['php', 'json', 'twig', 'xlf', 'yaml', 'md', 'js', 'css'];

    public function testNothingTheRepositoryShipsUsesTheOtherSpelling(): void
    {
        $root = \dirname(__DIR__, 2);

        $files = [$root.'/README.md', $root.'/composer.json', ...self::shippedFilesUnder($root.'/src')];
        self::assertGreaterThan(2, \count($files), 'the sweep found nothing under src/, so it is sweeping nothing');

        $offenders = [];
        foreach ($files as $file) {
            if (false !== stripos((string) file_get_contents($file), self::OTHER_SPELLING)) {
                $offenders[] = substr($file, \strlen($root) + 1);
            }
        }

        self::assertSame([], $offenders, 'these files spell it the other way; the word is "organization"');
    }

    /**
     * @return list<string>
     */
    private static function shippedFilesUnder(string $directory): array
    {
        $found = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if (!$file instanceof \SplFileInfo || !\in_array($file->getExtension(), self::SWEPT_EXTENSIONS, true)) {
                continue;
            }

            if (str_contains($file->getPathname(), '/tests/')) {
                continue;
            }

            $found[] = $file->getPathname();
        }

        sort($found);

        return $found;
    }
}
