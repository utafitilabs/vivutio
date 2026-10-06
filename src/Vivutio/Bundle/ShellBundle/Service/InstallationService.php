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

namespace Vivutio\Bundle\ShellBundle\Service;

use Composer\InstalledVersions;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Symfony\Component\HttpKernel\Kernel;
use Vivutio\Bundle\ShellBundle\Model\InstalledPackage;

/**
 * What this installation is made of, read from it rather than remembered: the
 * vivutio packages Composer installed, each with the description its own
 * composer.json gives, and the platform under it. It names no module.
 *
 * A package that the core replaces answers to Composer under its own name too,
 * with no directory; only what has an install path is installed.
 *
 * @see https://getcomposer.org/doc/07-runtime.md#installed-versions
 */
final readonly class InstallationService
{
    public const string CORE = 'vivutio/vivutio';
    private const string VENDOR = 'vivutio/';

    public function __construct(
        private Connection $connection,
        private string $environment,
    ) {
    }

    /**
     * The core first, then every other vivutio package by name.
     *
     * @return list<InstalledPackage>
     */
    public function packages(): array
    {
        $found = [];
        foreach (InstalledVersions::getAllRawData() as $set) {
            foreach ($set['versions'] as $name => $entry) {
                if (!str_starts_with($name, self::VENDOR) || isset($found[$name]) || !isset($entry['install_path'])) {
                    continue;
                }
                $version = \is_string($entry['pretty_version'] ?? null) ? ltrim($entry['pretty_version'], 'v') : '';
                $found[$name] = new InstalledPackage($name, $version, self::description($entry['install_path']));
            }
        }
        uksort($found, static fn (string $a, string $b): int => [self::CORE !== $a, $a] <=> [self::CORE !== $b, $b]);

        return array_values($found);
    }

    /**
     * The platform under it, in the order the page says it.
     *
     * @return array<string, string>
     */
    public function runtime(): array
    {
        $platform = $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform ? 'PostgreSQL' : 'Database';

        return [
            'PHP' => \PHP_VERSION,
            'Symfony' => Kernel::VERSION,
            'Database' => $platform.' '.$this->connection->getServerVersion(),
            'Environment' => $this->environment,
        ];
    }

    private static function description(string $installPath): string
    {
        $manifest = rtrim($installPath, '/').'/composer.json';
        if (!is_file($manifest)) {
            return '';
        }
        $read = json_decode((string) file_get_contents($manifest), true);

        return \is_array($read) && \is_string($read['description'] ?? null) ? $read['description'] : '';
    }
}
