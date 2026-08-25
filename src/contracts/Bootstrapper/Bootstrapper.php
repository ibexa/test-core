<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\Test\Core\Bootstrapper;

use Ibexa\Contracts\Test\Core\IbexaTestKernel;
use LogicException;
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
 * Kernel creation and database preparation are delegated to {@see KernelProviderInterface} and
 * {@see DatabasePreparerInterface}, both injectable via the constructor, defaulting to
 * {@see KernelProvider} and {@see DatabasePreparer} (today's behavior) when nothing is passed.
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

    private KernelProviderInterface $kernelProvider;

    private DatabasePreparerInterface $databasePreparer;

    public function __construct(
        ?KernelProviderInterface $kernelProvider = null,
        ?DatabasePreparerInterface $databasePreparer = null
    ) {
        $this->kernelProvider = $kernelProvider ?? new KernelProvider();
        $this->databasePreparer = $databasePreparer ?? new DatabasePreparer();
    }

    /**
     * @param array<string, mixed> $options
     *
     * @throws \Exception
     */
    public function bootstrap(
        ?string $kernelClass = null,
        array $options = []
    ): IbexaTestKernel {
        $kernel = $this->kernelProvider->getKernel($kernelClass);

        $testContainer = self::getService($kernel->getContainer(), 'test.service_container', ContainerInterface::class);
        $hooksExecutor = self::getService($testContainer, HooksExecutorInterface::class, HooksExecutorInterface::class);

        $options = self::resolveOptions($hooksExecutor, $options);
        $ownOptions = $options[self::class];

        $this->databasePreparer->prepareDatabase($kernel, $ownOptions[self::OPTION_SCHEMA_UPDATE]);
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
}
