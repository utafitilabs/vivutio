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

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Vivutio\Core\Tests\Application\Kernel;

/**
 * A specification that starts from a database with nothing in it and runs the
 * commands an installation runs. The database is handed back empty.
 */
abstract class MigrationsTestCase extends KernelTestCase
{
    protected Connection $connection;

    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    protected function setUp(): void
    {
        self::bootKernel();

        $connection = static::getContainer()->get('doctrine.dbal.default_connection');
        self::assertInstanceOf(Connection::class, $connection);
        $this->connection = $connection;

        $this->emptyTheDatabase();
    }

    protected function tearDown(): void
    {
        $this->emptyTheDatabase();
        $this->connection->close();

        parent::tearDown();

        // A booted kernel leaves its exception handlers on the stack, and
        // PHPUnit fails a test that ends with more of them than it began with.
        while (true) {
            $previous = set_exception_handler(static fn () => null);
            restore_exception_handler();
            if (null === $previous) {
                break;
            }
            restore_exception_handler();
        }
    }

    protected function emptyTheDatabase(): void
    {
        $this->connection->executeStatement('DROP SCHEMA IF EXISTS public CASCADE');
        $this->connection->executeStatement('CREATE SCHEMA public');
    }

    protected function migrate(): void
    {
        $this->console('doctrine:migrations:migrate', ['--no-interaction' => true, 'version' => 'latest']);
    }

    /**
     * @param array<string, mixed> $arguments
     */
    protected function console(string $command, array $arguments = []): string
    {
        $kernel = self::$kernel;
        self::assertNotNull($kernel);

        $application = new Application($kernel);
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);

        $output = new BufferedOutput();
        $status = $application->run(new ArrayInput(['command' => $command] + $arguments), $output);
        $text = $output->fetch();

        self::assertSame(0, $status, \sprintf('`%s` failed:%s%s', $command, \PHP_EOL, $text));

        return $text;
    }

    /**
     * @return list<string>
     */
    protected function tableNames(): array
    {
        /** @var list<string> $names */
        $names = $this->connection->fetchFirstColumn(
            "SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename"
        );

        return $names;
    }
}
