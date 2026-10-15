<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\Test\Core\Bootstrapper;

use Doctrine\DBAL\Connection;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @internal
 */
final class DatabasePreparer implements DatabasePreparerInterface
{
    private ConsoleCommandRunner $consoleCommandRunner;

    public function __construct()
    {
        $this->consoleCommandRunner = new ConsoleCommandRunner();
    }

    public function prepareDatabase(
        KernelInterface $kernel,
        bool $runSchemaUpdate
    ): void {
        $application = new Application($kernel);
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);

        // In-memory SQLite (e.g. "sqlite://:memory:") is deliberately unsupported: it can't
        // participate in the per-test DAMA transaction/rollback most consumers rely on, and its
        // connection params carry neither a "path" nor a "dbname" doctrine:database:drop could act
        // on — see doctrine.php's default DATABASE_URL, which points at a real file for this reason.
        $dropDatabaseArgs = [
            'command' => 'doctrine:database:drop',
            '--force' => '1',
            '--quiet' => true,
        ];

        // "--if-exists" makes the command call the platform's listDatabases() to check first —
        // unsupported by SQLite (it has no concept of "a list of databases" to enumerate), and
        // unneeded there anyway: SqliteSchemaManager::dropDatabase() already no-ops safely for a
        // file that doesn't exist. Every other supported platform still needs it, to avoid an error
        // dropping a database that was never created (e.g. this bootstrap's very first run).
        // $_ENV takes precedence to match doctrine.php's own source of truth for this value.
        $databaseUrl = $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL');
        $isSqlite = is_string($databaseUrl) && str_starts_with($databaseUrl, 'sqlite');
        if (!$isSqlite) {
            $dropDatabaseArgs['--if-exists'] = '1';
        }

        $this->consoleCommandRunner->run($application, $dropDatabaseArgs);

        // SQLite has no CREATE DATABASE. DBAL 4's SQLitePlatform::getCreateDatabaseSQL() throws
        // NotSupported where DBAL 3 left it to AbstractPlatform, so the command now fails outright.
        // Nothing is lost by skipping it: the file is created when the connection opens, as long
        // as its directory exists.
        if ($isSqlite) {
            $this->createSqliteDatabaseDirectory($kernel);
        } else {
            $this->consoleCommandRunner->run($application, [
                'command' => 'doctrine:database:create',
                '--quiet' => true,
            ]);
        }

        if ($runSchemaUpdate) {
            $this->consoleCommandRunner->run($application, [
                'command' => 'doctrine:schema:update',
                '--em' => 'ibexa_default',
                '--force' => true,
                '--quiet' => true,
            ]);
        }
    }

    /**
     * SQLite creates the database file when the connection opens, but not the directory it is in
     * (e.g. "var/" on a fresh checkout). Symfony 5.4 created "var/log" while building the kernel
     * container, which hid this; Symfony 7 no longer does.
     *
     * The kernel is already booted by the doctrine:database:drop run before this.
     */
    private function createSqliteDatabaseDirectory(KernelInterface $kernel): void
    {
        $connection = $kernel->getContainer()->get('database_connection');
        if (!$connection instanceof Connection) {
            return;
        }

        // DoctrineBundle's own doctrine:database:create reads the path the same way.
        $path = $connection->getParams()['path'] ?? null;
        if (!is_string($path) || $path === '') {
            throw new RuntimeException(
                'SQLite test database needs a file path, in-memory SQLite is not supported. Check DATABASE_URL.'
            );
        }

        $directory = dirname($path);
        if (!is_dir($directory) && !@mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Unable to create SQLite database directory "%s".', $directory));
        }
    }
}
