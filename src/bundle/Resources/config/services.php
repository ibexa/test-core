<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Ibexa\Contracts\Test\Core\Bootstrapper\DatabaseSchemaHook;
use Ibexa\Contracts\Test\Core\Bootstrapper\DefaultFixtureProvider;
use Ibexa\Contracts\Test\Core\Bootstrapper\DefaultSchemaFilesProvider;
use Ibexa\Contracts\Test\Core\Bootstrapper\FixtureHook;
use Ibexa\Contracts\Test\Core\Bootstrapper\FixtureProviderInterface;
use Ibexa\Contracts\Test\Core\Bootstrapper\HooksExecutorInterface;
use Ibexa\Contracts\Test\Core\Bootstrapper\PurgeIndexAfterFixturesHook;
use Ibexa\Contracts\Test\Core\Bootstrapper\PurgeSearchIndexHook;
use Ibexa\Contracts\Test\Core\Bootstrapper\SchemaFilesProviderInterface;
use Ibexa\Test\Core\Bootstrapper\FixtureKernelMethodProvider;
use Ibexa\Test\Core\Bootstrapper\FixtureParameterProvider;
use Ibexa\Test\Core\Bootstrapper\FixtureProviderChain;
use Ibexa\Test\Core\Bootstrapper\HooksExecutor;
use Ibexa\Test\Core\Bootstrapper\SchemaFilesKernelMethodProvider;
use Ibexa\Test\Core\Bootstrapper\SchemaFilesParameterProvider;
use Ibexa\Test\Core\Bootstrapper\SchemaFilesProviderChain;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->parameters()
        // null (not []): an unset/unconfigured parameter must be distinguishable from a consumer
        // deliberately configuring an empty list, since only the latter should stop the fallback
        // chain in *ProviderChain — see SchemaFilesProviderInterface's docblock.
        ->set('ibexa.test.schema_files', null)
        ->set('ibexa.test.fixture_files', null);

    $services = $containerConfigurator->services();
    $services->defaults()
        ->autowire()
        ->autoconfigure()
        ->private();

    $services->alias(HooksExecutorInterface::class, HooksExecutor::class)
        ->public();

    $services->set(HooksExecutor::class)
        ->public()
        // Indexed by service id (the "id" attribute isn't set by any hook's tag, so every hook
        // falls back to its own service id as the key) while staying a plain priority-sorted
        // iterator - tagged_locator() would also index by service id, but its ServiceLocator
        // wrapper re-sorts entries alphabetically by key, destroying the priority order hooks rely on.
        ->arg('$hooks', tagged_iterator('ibexa.test.bootstrapper.hook', 'id'));

    $services->set(DefaultSchemaFilesProvider::class)
        ->arg('$kernel', service('kernel'));

    $services->set(DefaultFixtureProvider::class);

    $services->set(SchemaFilesKernelMethodProvider::class)
        ->arg('$kernel', service('kernel'))
        ->tag(SchemaFilesProviderInterface::TAG, ['priority' => 0]);

    $services->set(SchemaFilesParameterProvider::class)
        ->arg('$schemaFiles', '%ibexa.test.schema_files%')
        ->tag(SchemaFilesProviderInterface::TAG, ['priority' => 100]);

    $services->set(SchemaFilesProviderChain::class)
        ->arg('$providers', tagged_iterator(SchemaFilesProviderInterface::TAG));

    $services->set(FixtureKernelMethodProvider::class)
        ->arg('$kernel', service('kernel'))
        ->tag(FixtureProviderInterface::TAG, ['priority' => 0]);

    $services->set(FixtureParameterProvider::class)
        ->arg('$fixtureFiles', '%ibexa.test.fixture_files%')
        ->tag(FixtureProviderInterface::TAG, ['priority' => 100]);

    $services->set(FixtureProviderChain::class)
        ->arg('$providers', tagged_iterator(FixtureProviderInterface::TAG));

    $services->set(DatabaseSchemaHook::class)
        ->arg('$provider', service(SchemaFilesProviderChain::class))
        ->tag('ibexa.test.bootstrapper.hook', ['priority' => 1000]);

    $services->set(FixtureHook::class)
        ->arg('$provider', service(FixtureProviderChain::class))
        ->tag('ibexa.test.bootstrapper.hook', ['priority' => 900]);

    $services->set(PurgeSearchIndexHook::class)
        ->arg('$handler', service('ibexa.spi.search'))
        ->tag('ibexa.test.bootstrapper.hook', ['priority' => 100]);

    $services->set(PurgeIndexAfterFixturesHook::class)
        ->arg('$handler', service('ibexa.spi.search'))
        ->tag('ibexa.test.bootstrapper.hook', ['priority' => 800]);
};
