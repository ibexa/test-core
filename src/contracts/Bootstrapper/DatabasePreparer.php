<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\Test\Core\Bootstrapper;

use Ibexa\Contracts\Test\Core\IbexaTestKernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;

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
        IbexaTestKernel $kernel,
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
        if (!is_string($databaseUrl) || !str_starts_with($databaseUrl, 'sqlite')) {
            $dropDatabaseArgs['--if-exists'] = '1';
        }

        $this->consoleCommandRunner->run($application, $dropDatabaseArgs);

        $this->consoleCommandRunner->run($application, [
            'command' => 'doctrine:database:create',
            '--quiet' => true,
        ]);

        if ($runSchemaUpdate) {
            $this->consoleCommandRunner->run($application, [
                'command' => 'doctrine:schema:update',
                '--em' => 'ibexa_default',
                '--force' => true,
                '--quiet' => true,
            ]);
        }
    }
}
