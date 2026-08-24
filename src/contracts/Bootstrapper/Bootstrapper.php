<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\Test\Core\Bootstrapper;

use Ibexa\Contracts\Test\Core\IbexaTestKernel;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @experimental
 *
 * Entry point for a package's `tests/integration/bootstrap.php`. Boots the test kernel, prepares the
 * database, and runs every registered {@see HookInterface} (schema import, fixture import, and whatever
 * else a bundle present in the kernel contributes — e.g. ibexa/migrations tags its own hook to run
 * migrations, with no changes needed here).
 *
 * `$options` is a top-level array with two kinds of keys:
 *  - schema_update (bool, default true): Bootstrapper's own option — run `doctrine:schema:update`
 *    against the ORM-mapped schema. Not read by any Hook.
 *  - HookClass::class => [...]: each hook's own options, under a key equal to that hook's own
 *    service id (which, for the built-in hooks below, is their own FQCN), resolved against whatever
 *    that hook declares in {@see HookInterface::configureOptions()}. For example:
 *      - \Ibexa\Test\Core\Bootstrapper\SchemaHook::class => ['schema' => false]
 *      - \Ibexa\Test\Core\Bootstrapper\FixtureHook::class => ['fixtures' => false]
 *      - \Ibexa\Test\Core\Bootstrapper\PurgeIndexHook::class => ['purge_index' => true]
 *
 * Other hooks (including ones contributed by a downstream bundle, e.g. ibexa/migrations' own
 * MigrationHook) may define and read their own FQCN-keyed options sub-array the same way; this
 * class does not need to know about them.
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

        $kernel = new $kernelClass('test', true);
        $kernel->boot();

        $this->prepareDatabase($kernel, $options);

        $testContainer = self::getContainer($kernel);
        $testContainer->get(HooksExecutorInterface::class)->execute($options);

        return $kernel;
    }

    private static function getContainer(KernelInterface $kernel): ContainerInterface
    {
        try {
            return $kernel->getContainer()->get('test.service_container');
        } catch (ServiceNotFoundException $e) {
            throw new LogicException(
                'Could not find service "test.service_container". Try updating the "framework.test" config to "true".',
                0,
                $e,
            );
        }
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
