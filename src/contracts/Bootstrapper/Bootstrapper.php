<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\Test\Core\Bootstrapper;

use Ibexa\Contracts\Test\Core\IbexaTestKernel;
use LogicException;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @experimental
 *
 * Entry point for a package's `tests/integration/bootstrap.php`. Boots the test kernel, prepares the
 * database, and runs every registered {@see HookInterface} (schema import, fixture import, and whatever
 * else a bundle present in the kernel contributes — e.g. ibexa/migrations tags its own hook to run
 * migrations, with no changes needed here).
 *
 * `$options` is a top-level array keyed by FQCN (or, for a hook contributed by a downstream bundle,
 * its own service id) — each key's own sub-array is resolved against that key's own OptionsResolver,
 * and any key that isn't recognized makes the whole array fail to resolve, e.g.:
 *  - self::class => [self::OPTION_SCHEMA_UPDATE => false]: Bootstrapper's own options —
 *    whether to run `doctrine:schema:update` against the ORM-mapped schema, and whether to shut the
 *    kernel down before returning it. Not read by any Hook.
 *  - HookClass::class => [...]: each hook's own options, resolved against whatever that hook
 *    declares in {@see HookInterface::configureOptions()}. For example:
 *      - DatabaseSchemaHook::class => [DatabaseSchemaHook::OPTION_LOAD_SCHEMA => false]
 *      - FixtureHook::class => [FixtureHook::OPTION_LOAD_FIXTURES => false]
 *      - PurgeSearchIndexHook::class => [PurgeSearchIndexHook::OPTION_PURGE_INDEX => true]
 *
 * Other hooks (including ones contributed by a downstream bundle, e.g. ibexa/migrations' own
 * MigrationHook) may define and read their own FQCN-keyed options sub-array the same way; this
 * class does not need to know about them.
 */
final class Bootstrapper
{
    public const OPTION_SCHEMA_UPDATE = 'schema_update';

    public const OPTION_SHUTDOWN_KERNEL = 'shutdown_kernel';

    /**
     * @param array<string, mixed> $options
     *
     * @throws \Exception
     */
    public function bootstrap(
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

        $testContainer = self::getService($kernel->getContainer(), 'test.service_container', ContainerInterface::class);
        $hooksExecutor = self::getService($testContainer, HooksExecutorInterface::class, HooksExecutorInterface::class);

        $options = self::resolveOptions($hooksExecutor, $options);
        $ownOptions = $options[self::class];

        $this->prepareDatabase($kernel, $ownOptions);
        $hooksExecutor->execute($options);

        if ($ownOptions[self::OPTION_SHUTDOWN_KERNEL]) {
            $kernel->shutdown();
        }

        return $kernel;
    }

    /**
     * Builds the top-level resolver — self::class for Bootstrapper's own options, plus whatever
     * {@see HooksExecutorInterface::configureOptions()} defines for each registered hook — and
     * resolves $options against it in one call, so any unrecognized key throws.
     *
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private static function resolveOptions(HooksExecutorInterface $hooksExecutor, array $options): array
    {
        $resolver = new OptionsResolver();
        $resolver->define(self::class)
            ->default([])
            ->allowedTypes('array')
            ->normalize(static function (Options $options, array $value): array {
                $ownResolver = new OptionsResolver();
                $ownResolver->define(self::OPTION_SCHEMA_UPDATE)
                    ->default(true)
                    ->allowedTypes('bool');
                $ownResolver->define(self::OPTION_SHUTDOWN_KERNEL)
                    ->default(true)
                    ->allowedTypes('bool');

                return $ownResolver->resolve($value);
            });
        $hooksExecutor->configureOptions($resolver);

        return $resolver->resolve($options);
    }

    /**
     * Fetches a service and asserts its type, in one place, so the "missing service" and
     * "wrong type" error messages can't drift out of sync between call sites the way
     * getContainer()'s and getHooksExecutor()'s hand-written copies of this once did.
     *
     * @template T of object
     *
     * @param class-string<T> $expectedType
     *
     * @return T
     */
    private static function getService(ContainerInterface $container, string $id, string $expectedType): object
    {
        try {
            $service = $container->get($id);
        } catch (ServiceNotFoundException $e) {
            throw new LogicException(sprintf(
                'Could not find service "%s". Try updating the "framework.test" config to "true".',
                $id,
            ), 0, $e);
        }

        if (!$service instanceof $expectedType) {
            throw new LogicException(sprintf(
                'Expected service "%s" to be an instance of "%s", got "%s".',
                $id,
                $expectedType,
                get_debug_type($service),
            ));
        }

        return $service;
    }

    /**
     * @param array<string, mixed> $ownOptions this class's own options, already resolved against
     *                                          the resolver built in {@see self::bootstrap()}
     */
    private function prepareDatabase(IbexaTestKernel $kernel, array $ownOptions): void
    {
        $application = new Application($kernel);
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);

        // $_ENV takes precedence to match doctrine.php's own source of truth for this value — it
        // reads $_ENV directly, not getenv(), so a DATABASE_URL set there without also calling
        // putenv() (e.g. via Symfony's Dotenv without usePutenv()) still resolves consistently here.
        $databaseUrl = $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL');
        if (is_string($databaseUrl) && !str_starts_with($databaseUrl, 'sqlite')) {
            self::runCommand($application, [
                'command' => 'doctrine:database:drop',
                '--if-exists' => '1',
                '--force' => '1',
                '--quiet' => true,
            ]);
        } elseif (is_string($databaseUrl)) {
            $sqliteFile = self::getSqliteFilePath($databaseUrl);
            if ($sqliteFile !== null && file_exists($sqliteFile)) {
                unlink($sqliteFile);
            }
        }

        self::runCommand($application, [
            'command' => 'doctrine:database:create',
            '--quiet' => true,
        ]);

        if ($ownOptions[self::OPTION_SCHEMA_UPDATE]) {
            self::runCommand($application, [
                'command' => 'doctrine:schema:update',
                '--em' => 'ibexa_default',
                '--force' => true,
                '--quiet' => true,
            ]);
        }
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @throws \Exception
     */
    private static function runCommand(Application $application, array $parameters): void
    {
        $exitCode = $application->run(new ArrayInput($parameters));
        if ($exitCode !== 0) {
            throw new RuntimeException(sprintf(
                'Command "%s" failed with exit code %d.',
                $parameters['command'],
                $exitCode,
            ));
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
