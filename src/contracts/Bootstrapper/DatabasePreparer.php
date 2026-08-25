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
    private SqliteFilePathResolver $sqliteFilePathResolver;

    private ConsoleCommandRunner $consoleCommandRunner;

    public function __construct()
    {
        $this->sqliteFilePathResolver = new SqliteFilePathResolver();
        $this->consoleCommandRunner = new ConsoleCommandRunner();
    }

    public function prepareDatabase(IbexaTestKernel $kernel, bool $runSchemaUpdate): void
    {
        $application = new Application($kernel);
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);

        // $_ENV takes precedence to match doctrine.php's own source of truth for this value — it
        // reads $_ENV directly, not getenv(), so a DATABASE_URL set there without also calling
        // putenv() (e.g. via Symfony's Dotenv without usePutenv()) still resolves consistently here.
        $databaseUrl = $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL');
        if (is_string($databaseUrl) && !str_starts_with($databaseUrl, 'sqlite')) {
            $this->consoleCommandRunner->run($application, [
                'command' => 'doctrine:database:drop',
                '--if-exists' => '1',
                '--force' => '1',
                '--quiet' => true,
            ]);
        } elseif (is_string($databaseUrl)) {
            $sqliteFile = $this->sqliteFilePathResolver->resolve($databaseUrl);
            if ($sqliteFile !== null && file_exists($sqliteFile)) {
                unlink($sqliteFile);
            }
        }

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
