<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\Test\Core\Bootstrapper;

use Ibexa\Contracts\Test\Core\IbexaTestKernel;
use LogicException;
use Psr\Container\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;

/**
 * @experimental
 *
 * Entry point for a package's `tests/integration/bootstrap.php`. Boots the test kernel, prepares the
 * database, and runs every registered {@see HookInterface} (schema import, fixture import, and whatever
 * else a bundle present in the kernel contributes — e.g. ibexa/migrations tags its own hook to run
 * migrations, with no changes needed here).
 *
 * Supported `$options` keys:
 *  - schema_update (bool, default true): run `doctrine:schema:update` against the ORM-mapped schema
 *  - schema (bool, default true): read by {@see \Ibexa\Test\Core\Bootstrapper\SchemaHook}
 *  - fixtures (bool, default true): read by {@see \Ibexa\Test\Core\Bootstrapper\FixtureHook}
 *  - purge_index (bool, default false): read by {@see \Ibexa\Test\Core\Bootstrapper\PurgeIndexHook}
 *
 * Other hooks may define and read their own option keys; this class does not need to know about them.
 */
final class Bootstrapper
{
    /**
     * @param array<string, mixed> $options
     */
    public function __invoke(
        ?string $kernelClass = null,
        array $options = []
    ): IbexaTestKernel {
        $kernelClass ??= $_ENV['KERNEL_CLASS'] ?? $_SERVER['KERNEL_CLASS'] ?? null;
        if ($kernelClass === null || !is_a($kernelClass, IbexaTestKernel::class, true)) {
            throw new LogicException(sprintf(
                'The kernel class "%s" must be a subclass of "%s". Ensure that the KERNEL_CLASS environment variable is set to a valid test kernel class.',
                $kernelClass ?? 'null',
                IbexaTestKernel::class,
            ));
        }

        /** @var IbexaTestKernel $kernel */
        $kernel = new $kernelClass('test', true);
        $kernel->boot();

        $this->prepareDatabase($kernel, $options);

        /** @var ContainerInterface $testContainer */
        $testContainer = $kernel->getContainer()->get('test.service_container');
        $testContainer->get(HooksExecutorInterface::class)->execute($options);

        return $kernel;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function prepareDatabase(IbexaTestKernel $kernel, array $options): void
    {
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

        if ($options['schema_update'] ?? true) {
            $application->run(new ArrayInput([
                'command' => 'doctrine:schema:update',
                '--em' => 'ibexa_default',
                '--force' => true,
                '--quiet' => true,
            ]));
        }
    }
}
