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

use Doctrine\Migrations\DependencyFactory;

/**
 * An installation runs `doctrine:migrations:migrate` and writes no version for
 * a table the core owns. So the versions the core ships build exactly the
 * tables its entities describe: after migrating an empty database there is
 * nothing left for a diff to write.
 */
final class MigrationsCoverSchemaTest extends MigrationsTestCase
{
    private const string IDENTITY_MIGRATIONS = 'Vivutio\\Bundle\\IdentityBundle\\Migrations';

    public function testEveryBundleThatOwnsTablesRegistersItsOwnMigrationsDirectory(): void
    {
        $factory = static::getContainer()->get('doctrine.migrations.dependency_factory');
        self::assertInstanceOf(DependencyFactory::class, $factory);

        $directories = $factory->getConfiguration()->getMigrationDirectories();

        self::assertArrayHasKey(self::IDENTITY_MIGRATIONS, $directories);
        self::assertSame(
            realpath(\dirname(__DIR__, 2).'/src/Vivutio/Bundle/IdentityBundle/migrations'),
            realpath($directories[self::IDENTITY_MIGRATIONS]),
        );
    }

    public function testTheMigrationsBuildEveryTableTheCoreOwnsAndNoOther(): void
    {
        $this->migrate();

        self::assertSame(['doctrine_migration_versions', 'identity_user'], $this->tableNames());
    }

    public function testAnEmptyDatabaseMigratedLeavesNothingForDiffToWrite(): void
    {
        $this->migrate();

        $before = $this->shippedVersionFiles();

        // --allow-empty-diff turns "there is nothing to write" from a thrown
        // exception into a message and exit status 0.
        // @see vendor/doctrine/migrations/src/Tools/Console/Command/DiffCommand.php
        $diff = $this->console('doctrine:migrations:diff', [
            '--no-interaction' => true,
            '--allow-empty-diff' => true,
            '--namespace' => self::IDENTITY_MIGRATIONS,
        ]);

        // A diff that found work wrote it. The file is taken back out before
        // asserting, so a failure leaves the repository as it was and carries
        // the SQL in its message.
        $drift = '';
        foreach (array_diff($this->shippedVersionFiles(), $before) as $file) {
            $drift .= (string) file_get_contents($file);
            unlink($file);
        }

        self::assertStringContainsString(
            'No changes detected in your mapping information.',
            $diff,
            'the shipped migrations and the shipped entities have drifted apart; diff wanted to write:'.\PHP_EOL.$drift,
        );
    }

    /**
     * @return list<string>
     */
    private function shippedVersionFiles(): array
    {
        $files = glob(\dirname(__DIR__, 2).'/src/Vivutio/Bundle/*/migrations/*.php') ?: [];
        sort($files);

        return $files;
    }
}
