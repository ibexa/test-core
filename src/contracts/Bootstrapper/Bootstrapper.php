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
 *      - SchemaHook::class => [SchemaHook::OPTION_LOAD_SCHEMA => false]
 *      - FixtureHook::class => [FixtureHook::OPTION_LOAD_FIXTURES => false]
 *      - PurgeIndexHook::class => [PurgeIndexHook::OPTION_PURGE_INDEX => true]
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
        self::getHooksExecutor($testContainer)->execute($options);

        return $kernel;
    }

    private static function getContainer(KernelInterface $kernel): ContainerInterface
    {
        try {
            $container = $kernel->getContainer()->get('test.service_container');
        } catch (ServiceNotFoundException $e) {
            throw new LogicException(
                'Could not find service "test.service_container". Try updating the "framework.test" config to "true".',
                0,
                $e,
            );
        }

        if (!$container instanceof ContainerInterface) {
            throw new LogicException(sprintf(
                'Expected service "test.service_container" to be an instance of "%s", got "%s".',
                ContainerInterface::class,
                get_debug_type($container),
            ));
        }

        return $container;
    }

    private static function getHooksExecutor(ContainerInterface $testContainer): HooksExecutorInterface
    {
        try {
            $executor = $testContainer->get(HooksExecutorInterface::class);
        } catch (ServiceNotFoundException $e) {
            throw new LogicException(sprintf(
                'Could not find service "%s". Try updating the "framework.test" config to "true".',
                HooksExecutorInterface::class,
            ), 0, $e);
        }

        if (!$executor instanceof HooksExecutorInterface) {
            throw new LogicException(sprintf(
                'Invalid executor service acquired. Expected %s, received %s.',
                HooksExecutorInterface::class,
                get_debug_type($executor),
            ));
        }

        return $executor;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function prepareDatabase(IbexaTestKernel $kernel, array $options): void
    {
        $application = new Application($kernel);
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);

        // $_ENV takes precedence to match doctrine.php's own source of truth for this value — it
        // reads $_ENV directly, not getenv(), so a DATABASE_URL set there without also calling
        // putenv() (e.g. via Symfony's Dotenv without usePutenv()) still resolves consistently here.
        $databaseUrl = $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL');
        if (is_string($databaseUrl) && !str_starts_with($databaseUrl, 'sqlite')) {
            $application->run(new ArrayInput([
                'command' => 'doctrine:database:drop',
                '--if-exists' => '1',
                '--force' => '1',
                '--quiet' => true,
            ]));
        } elseif (is_string($databaseUrl)) {
            $sqliteFile = self::getSqliteFilePath($databaseUrl);
            if ($sqliteFile !== null && file_exists($sqliteFile)) {
                unlink($sqliteFile);
            }
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

    /**
     * Extracts the filesystem path out of a sqlite DATABASE_URL, matching Doctrine's own
     * "sqlite://i@i/path/to/file.db" convention: the URL's own leading slash (the one separating
     * the fake "i@i" host from the path) is stripped, leaving the path as configured — relative to
     * the current working directory for a relative path, or, if the configured path is itself
     * absolute (e.g. "sqlite://i@i/%kernel.project_dir%/var/data.db"), still absolute, since only
     * one slash is stripped rather than every leading slash. Returns null for "sqlite://:memory:",
     * which has no file to clean up.
     *
     * @throws \LogicException if $databaseUrl uses one of Doctrine's other valid sqlite DSN forms
     *                         ("sqlite:///path" or "sqlite:////abs/path") — Doctrine's own
     *                         DriverManager special-cases and rewrites these before connecting, but
     *                         PHP's parse_url() can't parse them at all (returns false, not null),
     *                         so the file to clean up can't be determined; silently skipping
     *                         cleanup here would leave stale data for the next bootstrap run.
     */
    private static function getSqliteFilePath(string $databaseUrl): ?string
    {
        $path = parse_url($databaseUrl, PHP_URL_PATH);

        if ($path === null) {
            return null;
        }

        if ($path === false) {
            throw new LogicException(sprintf(
                'Could not determine a file path from sqlite DATABASE_URL "%s": PHP\'s parse_url()'
                . ' can\'t parse the "sqlite:///path" or "sqlite:////abs/path" DSN forms, even though'
                . ' Doctrine itself accepts them. Use the "sqlite://i@i/path/to/file.db" convention instead.',
                $databaseUrl,
            ));
        }

        return str_starts_with($path, '/') ? substr($path, 1) : $path;
    }
}
