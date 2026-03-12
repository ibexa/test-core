<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\Test\Core;

use Ibexa\Contracts\Core\Search\VersatileHandler;
use Ibexa\Contracts\Core\Test\Persistence\Fixture\FixtureImporter;
use Ibexa\Contracts\Migration\MigrationService;
use Ibexa\Migration\Repository\Migration;
use Ibexa\Tests\Core\Repository\LegacySchemaImporter;
use LogicException;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;

function bootstrap(
    ?string $kernelClass = null,
    bool $schemaUpdate = true,
    bool $purgeIndex = false,
): IbexaTestKernel {
    $kernelClass ??= $_ENV['KERNEL_CLASS'] ?? $_SERVER['KERNEL_CLASS'];
    if (!is_a($kernelClass, IbexaTestKernel::class, true)) {
        throw new LogicException(sprintf(
            'The kernel class "%s" must be a subclass of "%s". Ensure that KERNEL_CLASS environment variable is set to a valid test kernel class.',
            $kernelClass,
            IbexaTestKernel::class,
        ));
    }

    $kernel = new $kernelClass('test', true);
    $kernel->boot();

    $application = new Application($kernel);
    $application->setAutoExit(false);
    $application->setCatchExceptions(false);

    $databaseUrl = getenv('DATABASE_URL');
    if ($databaseUrl !== false && !str_starts_with($databaseUrl, 'sqlite')) {
        $application->run(new ArrayInput([
            'command' => 'doctrine:database:drop',
            '--if-exists' => '1',
            '--force' => '1',
            '--quiet' => true,
        ]));
    } elseif (file_exists('./test.db')) {
        unlink('./test.db');
    }

    $application->run(new ArrayInput([
        'command' => 'doctrine:database:create',
        '--quiet' => true,
    ]));

    if ($schemaUpdate) {
        $application->run(new ArrayInput([
            'command' => 'doctrine:schema:update',
            '--em' => 'ibexa_default',
            '--force' => true,
            '--quiet' => true,
        ]));
    }

    /** @var ContainerInterface $testContainer */
    $testContainer = $kernel->getContainer()->get('test.service_container');

    $schemaImporter = $testContainer->get(LegacySchemaImporter::class);
    foreach ($kernel->getSchemaFiles() as $file) {
        $schemaImporter->importSchema($file);
    }

    $fixtureImporter = $testContainer->get(FixtureImporter::class);
    foreach ($kernel->getFixtures() as $fixture) {
        $fixtureImporter->import($fixture);
    }

    foreach ($kernel->getMigrationFiles() as $migrationFile) {
        if (!class_exists(MigrationService::class)) {
            throw new LogicException(sprintf(
                '%s class not found. Install ibexa/migrations package to use migrations.',
                MigrationService::class,
            ));
        }
        $migrationService = $testContainer->get(MigrationService::class);
        $content = file_get_contents($migrationFile);
        if ($content === false) {
            throw new RuntimeException(sprintf('Failed to read "%s" contents', $migrationFile));
        }

        $migrationService->executeOne(new Migration(uniqid(), $content));
    }

    if ($purgeIndex) {
        /** @var VersatileHandler $handler */
        $handler = $testContainer->get('ibexa.spi.search');
        $handler->purgeIndex();
    }

    return $kernel;
}
